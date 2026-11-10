<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod;

/**
 * From when assets with this method are depreciated (`depreciatereconciliation`).
 */
enum DepreciationStart: string
{
    /** As of the revaluation date. */
    case FromReconciliation = 'from_reconciliation';

    /** In retrospect, back to the purchase date. */
    case FromPurchaseDate = 'from_purchase_date';
}
