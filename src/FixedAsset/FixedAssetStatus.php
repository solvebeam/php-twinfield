<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * Status of a fixed asset of the new fixed assets module.
 */
enum FixedAssetStatus: string
{
    case ToBeActivated = 'toBeActivated';
    case Active = 'active';
    case Inactive = 'inactive';
    /** Sold or scrapped. */
    case Disposed = 'disposed';
    case Deleted = 'deleted';
    case InactiveBySplit = 'inactiveBySplit';
    case InactiveByReversedSplit = 'inactiveByReversedSplit';
    /** Taken out of use. */
    case NotInUse = 'notInUse';
    case InactiveByMerge = 'inactiveByMerge';
    case InactiveByReversedMerge = 'inactiveByReversedMerge';
    /** Search filter: inactive for any reason. */
    case InactiveAnyReason = 'inactiveAnyReason';
    case InactiveByUndoSell = 'inactiveByUndoSell';
}
