<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * What a sales invoice would come to, from `calculate()`: the lines with their amounts, the
 * totals and the VAT. Nothing is saved, so there is no invoice number.
 *
 *   $calculation = $twinfield->salesInvoices()->calculate($input);
 *
 *   echo $calculation->totals->valueExcl, ' + ', $calculation->totals->vat(), ' VAT', PHP_EOL;
 */
final readonly class SalesInvoiceCalculation
{
    /**
     * @param non-empty-list<SalesInvoiceLine> $lines
     * @param list<SalesInvoiceVatLine> $vatLines
     */
    public function __construct(
        public array $lines,
        public SalesInvoiceTotals $totals,
        public array $vatLines,
    ) {
    }

    /**
     * From the `<salesinvoice>` element of a `calculateonly` response.
     *
     * @internal
     */
    public static function fromXml(XmlElement $invoice): self
    {
        $lines = array_map(SalesInvoiceLine::fromXml(...), $invoice->child('lines')->list('line'));

        if ($lines === []) {
            throw new UnexpectedResponseException(sprintf('%s/lines has no <line>.', $invoice->path));
        }

        return new self(
            lines: $lines,
            totals: SalesInvoiceTotals::fromXml($invoice->child('totals')),
            vatLines: array_map(SalesInvoiceVatLine::fromXml(...), $invoice->child('vatlines')->list('vatline')),
        );
    }
}
