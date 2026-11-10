<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Balance sheet accounts of an asset method (`balanceaccounts`). Each is null when not set,
 * which is valid in Twinfield.
 */
final readonly class BalanceAccounts
{
    public function __construct(
        /** Dimension group of the purchase value accounts. */
        public ?AccountReference $purchaseValueGroup,
        /** Purchase value balance sheet account. */
        public ?AccountReference $purchaseValue,
        /** Dimension group of the depreciation accounts. */
        public ?AccountReference $depreciationGroup,
        /** Cumulative depreciation balance sheet account. */
        public ?AccountReference $depreciation,
        /** Assets to activate balance sheet account. */
        public ?AccountReference $assetsToActivate,
        /** Revaluation of purchase value balance sheet account. */
        public ?AccountReference $reconciliation,
        /** Disposal value to be invoiced balance sheet account. */
        public ?AccountReference $toBeInvoiced,
    ) {
    }

    public static function fromXml(XmlElement $accounts): self
    {
        return new self(
            purchaseValueGroup: AccountReference::group($accounts->child('purchasevaluegroup')),
            purchaseValue: AccountReference::account($accounts->child('purchasevalue')),
            depreciationGroup: AccountReference::group($accounts->child('depreciationgroup')),
            depreciation: AccountReference::account($accounts->child('depreciation')),
            assetsToActivate: AccountReference::account($accounts->child('assetstoactivate')),
            reconciliation: AccountReference::account($accounts->child('reconciliation')),
            toBeInvoiced: AccountReference::account($accounts->child('tobeinvoiced')),
        );
    }
}
