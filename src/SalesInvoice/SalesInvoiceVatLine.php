<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\Performance;
use SolveBeam\Twinfield\Transaction\TransactionXml;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * VAT per VAT code of a sales invoice, calculated by Twinfield.
 */
final readonly class SalesInvoiceVatLine
{
    public function __construct(
        /** VAT code, e.g. `VH`. */
        public string $vatCode,
        /** Name of the VAT code; null when Twinfield gives none. */
        public ?string $vatName,
        public Amount $vatValue,
        /** Only for ICT VAT codes; null otherwise (Twinfield returns empty elements). */
        public ?Performance $performance,
    ) {
    }

    public static function fromXml(XmlElement $line): self
    {
        $vatCode = $line->child('vatcode');

        if ($vatCode->text() === '') {
            throw new UnexpectedResponseException(sprintf('%s is empty.', $vatCode->path));
        }

        $name = $vatCode->element->getAttribute('name');

        return new self(
            vatCode: $vatCode->text(),
            vatName: $name === '' ? null : $name,
            vatValue: TransactionXml::amount($line, 'vatvalue'),
            performance: TransactionXml::performance($line),
        );
    }
}
