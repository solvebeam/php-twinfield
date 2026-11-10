<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Profit and loss accounts of an asset method (`profitlossaccounts`). Each is null when not set,
 * which is valid in Twinfield.
 */
final readonly class ProfitLossAccounts
{
    public function __construct(
        /** Depreciation costs. */
        public ?AccountReference $depreciation,
        /** Disposal result. */
        public ?AccountReference $sales,
        /** Revaluation reserve. */
        public ?AccountReference $reconciliation,
    ) {
    }

    public static function fromXml(XmlElement $accounts): self
    {
        return new self(
            depreciation: AccountReference::account($accounts->child('depreciation')),
            sales: AccountReference::account($accounts->child('sales')),
            reconciliation: AccountReference::account($accounts->child('reconciliation')),
        );
    }
}
