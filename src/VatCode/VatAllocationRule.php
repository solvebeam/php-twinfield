<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

/**
 * Allocation rule of a VAT rate (REST API): a transaction line Twinfield generates when the rate is
 * applied, e.g. the VAT line on the VAT account. References are GUIDs.
 */
final readonly class VatAllocationRule
{
    public function __construct(
        /** GUID of the rule. */
        public string $id,
        /** True for the line with the VAT amount. */
        public bool $isVatLine,
        /** Share of the VAT amount, e.g. `100`, or `-100` for reverse charge. */
        public Percentage $percentage,
        /** GUID of the general ledger account. */
        public ?string $generalLedgerAccountId,
        public ?string $costCenterId,
        /** GUID of the VAT group (the box of the VAT return). */
        public ?string $vatGroupId,
    ) {
    }
}
