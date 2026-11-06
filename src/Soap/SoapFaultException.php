<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use DOMElement;
use RuntimeException;

/**
 * Twinfield answered with a SOAP fault.
 *
 * Twinfield uses two forms of fault detail, each with its own subclass:
 *
 * - ClassicServiceFaultException for the classic web services (.asmx), see
 *   https://developers.twinfield.com/documentation/api/types/guidelines:
 *   `<detail><message>…</message><code>354</code><source>KeepAlive</source></detail>`
 * - CommandQueryFaultException for the Command & Query web services (.svc):
 *   `<detail><PeriodServiceFault><Code>YearNotFound</Code><Message>…</Message></PeriodServiceFault></detail>`
 *
 * This class itself is thrown for a fault without such a detail.
 */
class SoapFaultException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $faultCode = null,
        public readonly ?string $faultString = null,
    ) {
        parent::__construct($message);
    }

    public static function fromSoapResponse(SoapResponse $response): self
    {
        $faultCode = $response->faultCode();
        $faultString = $response->faultString();

        $detail = $response->faultDetail();
        $fields = $detail === null ? [] : self::childElements($detail);

        // Command & Query: the detail holds one typed fault element, e.g. <PeriodServiceFault>.
        if (!isset($fields['code']) && !isset($fields['message']) && count($fields) === 1) {
            $typed = array_values($fields)[0];
            $type = (string) $typed->localName;
            $typedFields = self::childElements($typed);
            $code = self::text($typedFields['code'] ?? null);
            $message = self::text($typedFields['message'] ?? null);

            if ($code !== null && $message !== null) {
                return new CommandQueryFaultException(
                    self::message($faultCode, $faultString ?? $message, [$type, 'code ' . $code]),
                    $faultCode,
                    $faultString,
                    $type,
                    $code,
                    $message,
                );
            }
        }

        $code = self::text($fields['code'] ?? null);
        $message = self::text($fields['message'] ?? null);
        $source = self::text($fields['source'] ?? null);

        if ($code !== null && $message !== null) {
            $details = ['code ' . $code, $source === null ? null : 'source ' . $source];

            return new ClassicServiceFaultException(
                self::message($faultCode, $faultString ?? $message, $details),
                $faultCode,
                $faultString,
                $code,
                $message,
                $source,
            );
        }

        return new self(self::message($faultCode, $faultString ?? 'Unknown SOAP fault', []), $faultCode, $faultString);
    }

    /**
     * @param list<?string> $details
     */
    private static function message(?string $faultCode, string $text, array $details): string
    {
        $message = sprintf('%s: %s', $faultCode ?? 'SOAP-FAULT', $text);
        $details = array_filter($details);

        if ($details !== []) {
            $message .= sprintf(' (%s)', implode(', ', $details));
        }

        return $message;
    }

    /**
     * Child elements by lowercase local name; the first one wins.
     *
     * @return array<string, DOMElement>
     */
    private static function childElements(DOMElement $parent): array
    {
        $children = [];

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[strtolower((string) $child->localName)] ??= $child;
            }
        }

        return $children;
    }

    private static function text(?DOMElement $element): ?string
    {
        $text = $element === null ? '' : trim($element->textContent);

        return $text === '' ? null : $text;
    }
}
