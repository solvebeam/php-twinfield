<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * What happened to a fixed asset, in its history (`actions()`).
 */
enum FixedAssetActionType: string
{
    case Created = 'created';
    case Edited = 'edited';
    case Activated = 'activated';
    case Depreciated = 'depreciated';
    case Deactivated = 'deactivated';
    case Revalued = 'revalued';
    case Disposed = 'disposed';
    case DisposalMadeUndone = 'disposalMadeUndone';
    case DepreciationReversed = 'depreciationReversed';
    case CommentAdded = 'commentAdded';
    case CommentDeleted = 'commentDeleted';
    case TransactionLinked = 'transactionLinked';
    case TransactionUnlinked = 'transactionUnlinked';
    case CreatedFromSplit = 'createdFromSplit';
    case DeactivatedBySplit = 'deactivatedBySplit';
    case DeactivatedByReversedSplit = 'deactivatedByReversedSplit';
    case ActivatedByReversedSplit = 'activatedByReversedSplit';
    case DeactivatedByUndoSplitReversed = 'deactivatedByUndoSplitReversed';
    case CreatedByManualConversion = 'createdByManualConversion';
    case Imported = 'imported';
    case PurchasedFromTwinfieldCompany = 'purchasedFromTwinfieldCompany';
    case DeactivatedByNotInUseReversed = 'deactivatedByNotInUseReversed';
    case DeactivatedByNotInUse = 'deactivatedByNotInUse';
    case CreatedFromMerge = 'createdFromMerge';
    case DeactivatedByMerge = 'deactivatedByMerge';
    case DeactivatedByReversedMerge = 'deactivatedByReversedMerge';
    case ActivatedByReversedMerge = 'activatedByReversedMerge';
    case DeactivatedByUndoSell = 'deactivatedByUndoSell';
    case HistoricalValuesAdded = 'historicalValuesAdded';
    case ManualTransactionLinked = 'manualTransactionLinked';
    case Deleted = 'deleted';
}
