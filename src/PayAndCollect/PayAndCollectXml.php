<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

use DateTimeImmutable;
use DOMElement;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Readers shared by the Pay & Collect models.
 *
 * @internal
 */
final class PayAndCollectXml
{
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * Text of a child element that must not be empty.
     */
    public static function string(ResultElement $element, string $name): string
    {
        $text = $element->string($name);

        if ($text === '') {
            throw new UnexpectedResponseException(sprintf('%s/%s is empty.', $element->path, $name));
        }

        return $text;
    }

    /**
     * `touched` and `modified`, e.g. of a `PayOverview` (`Touched`, `Modified`) or of a
     * `ModifyResponse` (`touched`, `modified`).
     *
     * @param list<PayAndCollectMessage> $messages
     */
    public static function version(
        ResultElement $element,
        string $touched,
        string $modified,
        array $messages = [],
    ): PayRunVersion {
        $touchedValue = $element->int($touched);

        if ($touchedValue < 0) {
            throw new UnexpectedResponseException(sprintf(
                '%s/%s is %d, expected 0 or more.',
                $element->path,
                $touched,
                $touchedValue,
            ));
        }

        return new PayRunVersion(
            touched: $touchedValue,
            modified: $element->dateTime($modified),
            modifiedValue: self::string($element, $modified),
            messages: $messages,
        );
    }

    /**
     * An `xs:dateTime` as a date, e.g. a pay date `2026-11-12T00:00:00`.
     */
    public static function date(ResultElement $element, string $name): DateTimeImmutable
    {
        return $element->dateTime($name)->setTime(0, 0);
    }

    /**
     * `CustomSettings`: an `ArrayOfArrayOfString` of `[name, value]` pairs (manual, page 6).
     * Twinfield leaves the element out when the run has none.
     *
     * @return array<string, string>
     */
    public static function settings(ResultElement $element, string $name): array
    {
        $settings = [];

        if ($element->nullableChild($name) === null) {
            return $settings;
        }

        foreach ($element->list($name, 'ArrayOfString') as $pair) {
            $strings = [];

            foreach ($pair->element->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $strings[] = $child->localName === 'string'
                        ? $child->textContent
                        : throw new UnexpectedResponseException(sprintf(
                            '%s contains <%s>, expected <string>.',
                            $pair->path,
                            $child->localName,
                        ));
                }
            }

            if (count($strings) !== 2 || $strings[0] === '') {
                throw new UnexpectedResponseException(sprintf(
                    '%s has %d values, expected a name and a value.',
                    $pair->path,
                    count($strings),
                ));
            }

            $settings[$strings[0]] = $strings[1];
        }

        return $settings;
    }

    /**
     * Messages of a result, e.g. `list('AuthorizeResult', 'MessageOfAuthoriseResult')`. The result
     * element has `minOccurs="0"`: left out means no messages.
     *
     * @return list<PayAndCollectMessage>
     */
    public static function messages(ResultElement $response, string $result, string $itemName): array
    {
        if ($response->nullableChild($result) === null) {
            return [];
        }

        return array_map(
            static fn (ResultElement $message): PayAndCollectMessage => new PayAndCollectMessage(
                type: $message->enum('Type', PayAndCollectMessageType::class),
                text: self::string($message, 'Text'),
                code: self::string($message, 'Code'),
                parameters: $message->nullableChild('Parameters') === null
                    ? []
                    : array_map(self::parameter(...), $message->list('Parameters', 'string')),
            ),
            $response->list($result, $itemName),
        );
    }

    /**
     * Null for `<string xsi:nil="true"/>`.
     */
    private static function parameter(ResultElement $parameter): ?string
    {
        return $parameter->element->getAttributeNS(self::XSI_NS, 'nil') === 'true'
            ? null
            : $parameter->element->textContent;
    }
}
