<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

use RuntimeException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The sales invoice does not exist in the company.
 */
final class SalesInvoiceNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $invoiceType,
        public readonly int $invoiceNumber,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?ProcessXmlException $previous = null,
    ) {
        parent::__construct(
            sprintf('Sales invoice %s %d not found: %s', $invoiceType, $invoiceNumber, $reason),
            0,
            $previous,
        );
    }

    /**
     * The not-found exception when Twinfield refused a read because the invoice does not
     * exist, or null for any other error.
     *
     * Twinfield then answers with an empty root element with one error:
     * `<salesinvoice msgtype="error" msg="Factuur 999999999 van type FACTUUR bestaat niet." result="0"/>`.
     * The message is in the language of the user, and also an unknown invoice type gives it,
     * so the structure decides, not the text.
     */
    public static function fromProcessXml(
        ProcessXmlException $exception,
        string $invoiceType,
        int $invoiceNumber,
    ): ?self {
        $result = $exception->result;
        $errors = $result->errors();
        $root = XmlElement::root($result);

        $isNotFound = $root->element->localName === 'salesinvoice'
            && count($errors) === 1
            && $errors[0]->path === 'salesinvoice'
            && $root->nullableChild('header') === null;

        return $isNotFound ? new self($invoiceType, $invoiceNumber, $errors[0]->text, $exception) : null;
    }
}
