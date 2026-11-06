<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use DOMElement;
use RuntimeException;

/**
 * Twinfield answered with a SOAP fault.
 *
 * The fault detail, when present, is available as properties. Twinfield uses two forms:
 *
 * - Classic web services (.asmx), see https://developers.twinfield.com/documentation/api/types/guidelines:
 *   `<detail><message>…</message><code>354</code><source>KeepAlive</source></detail>`
 * - Command & Query web services (.svc):
 *   `<detail><PeriodServiceFault><Code>YearNotFound</Code><Message>…</Message></PeriodServiceFault></detail>`
 *
 * Messages are language dependent; use `detailCode` to act on specific errors.
 */
final class SoapFaultException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $faultCode = null,
        public readonly ?string $faultString = null,
        /** Fault type of a Command & Query service, e.g. `PeriodServiceFault`, `SecurityFault` or `ValidationFault`. */
        public readonly ?string $detailType = null,
        /** E.g. `354` (classic) or `YearNotFound` (Command & Query). */
        public readonly ?string $detailCode = null,
        public readonly ?string $detailMessage = null,
        /** Name of the called method (classic services only). */
        public readonly ?string $source = null,
    ) {
        parent::__construct($message);
    }

    public static function fromSoapResponse(SoapResponse $response): self
    {
        $faultCode = $response->faultCode();
        $faultString = $response->faultString();

        $detail = $response->faultDetail();
        $detailType = null;
        $fields = $detail === null ? null : self::childElements($detail);

        // Command & Query: the detail holds one typed fault element, e.g. <PeriodServiceFault>.
        if ($fields !== null && !isset($fields['code']) && !isset($fields['message']) && count($fields) === 1) {
            $typed = array_values($fields)[0];
            $detailType = $typed->localName;
            $fields = self::childElements($typed);
        }

        $detailCode = self::text($fields['code'] ?? null);
        $detailMessage = self::text($fields['message'] ?? null);
        $source = self::text($fields['source'] ?? null);

        $text = $faultString ?? $detailMessage ?? 'Unknown SOAP fault';
        $message = sprintf('%s: %s', $faultCode ?? 'SOAP-FAULT', $text);

        $details = array_filter([
            $detailType,
            $detailCode === null ? null : 'code ' . $detailCode,
            $source === null ? null : 'source ' . $source,
        ]);

        if ($details !== []) {
            $message .= sprintf(' (%s)', implode(', ', $details));
        }

        return new self($message, $faultCode, $faultString, $detailType, $detailCode, $detailMessage, $source);
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
