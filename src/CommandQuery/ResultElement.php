<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CommandQuery;

use DateTimeImmutable;
use DOMElement;
use Exception;
use SolveBeam\Twinfield\Soap\SoapResponse;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Strict reader for the result of a Command & Query web service.
 *
 *   $result = $twinfield->query('PeriodService', new Query('GetYears'), 'GetYearsResult');
 *   $years = $result->ints('Years');
 *
 * Every reader throws an UnexpectedResponseException when the element is
 * missing, nil or has an invalid value, with the path in the message.
 */
final readonly class ResultElement
{
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    public function __construct(
        public DOMElement $element,
        private string $path,
    ) {
    }

    /**
     * The `Result` element of a query response, checked against the expected `i:type`, e.g. `GetPeriodsResult`.
     *
     * The QueryResponse is a WCF message contract, so `<Result>` is directly in the SOAP body.
     */
    public static function fromQueryResponse(SoapResponse $response, string $resultType): self
    {
        $payload = $response->payloadElement();

        if ($payload === null || $payload->localName !== 'Result') {
            throw new UnexpectedResponseException(sprintf(
                'Expected a query <Result>, received %s.',
                $payload === null ? 'an empty SOAP body' : '<' . $payload->localName . '>',
            ));
        }

        if ($payload->getAttributeNS(self::XSI_NS, 'nil') === 'true') {
            throw new UnexpectedResponseException('Query result is nil.');
        }

        $result = new self($payload, 'Result');
        $type = $result->element->getAttributeNS(self::XSI_NS, 'type');
        $localType = str_contains($type, ':') ? substr($type, (int) strrpos($type, ':') + 1) : $type;

        if ($localType !== $resultType) {
            throw new UnexpectedResponseException(sprintf(
                'Expected query result of type "%s", received "%s".',
                $resultType,
                $type,
            ));
        }

        return new self($result->element, $resultType);
    }

    public function child(string $name): self
    {
        foreach ($this->element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                if ($child->getAttributeNS(self::XSI_NS, 'nil') === 'true') {
                    throw new UnexpectedResponseException(sprintf('%s/%s is nil.', $this->path, $name));
                }

                return new self($child, $this->path . '/' . $name);
            }
        }

        throw new UnexpectedResponseException(sprintf('%s/%s is missing.', $this->path, $name));
    }

    public function string(string $name): string
    {
        return $this->child($name)->text();
    }

    public function int(string $name): int
    {
        return $this->child($name)->asInt();
    }

    public function bool(string $name): bool
    {
        $child = $this->child($name);

        return match ($child->text()) {
            'true' => true,
            'false' => false,
            default => throw $child->invalid('a boolean'),
        };
    }

    /**
     * An `xs:dateTime` such as `2021-02-28T00:00:00`.
     */
    public function dateTime(string $name): DateTimeImmutable
    {
        return $this->child($name)->asDateTime();
    }

    /**
     * An `xs:dateTime` that may be nil (`i:nil="true"`). Only use this where nil is
     * valid Twinfield behaviour; a missing element still throws.
     */
    public function nullableDateTime(string $name): ?DateTimeImmutable
    {
        return $this->isNil($name) ? null : $this->dateTime($name);
    }

    private function isNil(string $name): bool
    {
        foreach ($this->element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                return $child->getAttributeNS(self::XSI_NS, 'nil') === 'true';
            }
        }

        return false;
    }

    private function asDateTime(): DateTimeImmutable
    {
        $text = $this->text();

        if (preg_match('~^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}~', $text) !== 1) {
            throw $this->invalid('a date and time');
        }

        try {
            return new DateTimeImmutable($text);
        } catch (Exception) {
            throw $this->invalid('a date and time');
        }
    }

    /**
     * Items of a list element, e.g. `list('Periods', 'Period')`.
     *
     * @return list<self>
     */
    public function list(string $name, string $itemName): array
    {
        $list = $this->child($name);
        $items = [];
        $index = 0;

        foreach ($list->element->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $index++;

            if ($child->localName !== $itemName) {
                throw new UnexpectedResponseException(sprintf(
                    '%s contains <%s>, expected <%s>.',
                    $list->path,
                    $child->localName,
                    $itemName,
                ));
            }

            $items[] = new self($child, sprintf('%s/%s[%d]', $list->path, $itemName, $index));
        }

        return $items;
    }

    /**
     * Items of an `ArrayOfint`, e.g. `ints('Years')`.
     *
     * @return list<int>
     */
    public function ints(string $name): array
    {
        return array_map(static fn (self $item): int => $item->asInt(), $this->list($name, 'int'));
    }

    private function text(): string
    {
        return trim($this->element->textContent);
    }

    private function asInt(): int
    {
        $text = $this->text();

        if (preg_match('~^-?\d+$~', $text) !== 1) {
            throw $this->invalid('an integer');
        }

        return (int) $text;
    }

    private function invalid(string $expected): UnexpectedResponseException
    {
        return new UnexpectedResponseException(sprintf(
            '%s is not %s: "%s".',
            $this->path,
            $expected,
            $this->text(),
        ));
    }
}
