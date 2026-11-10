<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\TransactionXml;

/**
 * Totals of a sales invoice, calculated by Twinfield.
 */
final readonly class SalesInvoiceTotals
{
    public function __construct(
        /** Total without VAT. */
        public Amount $valueExcl,
        /** Total with VAT. */
        public Amount $valueInc,
    ) {
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $totals): self
    {
        return new self(
            valueExcl: TransactionXml::amount($totals, 'valueexcl'),
            valueInc: TransactionXml::amount($totals, 'valueinc'),
        );
    }

    /**
     * The VAT: total with VAT minus total without VAT.
     */
    public function vat(): Amount
    {
        return $this->valueInc->minus($this->valueExcl);
    }
}
