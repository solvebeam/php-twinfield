<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ClassicFixedAsset;

/**
 * Where a fixed asset is in its life (`fixedassets/status`). Twinfield sets it: it is always
 * locked, so it cannot be sent.
 *
 * The documentation writes `ToBeActivated`, `Active`, `Inactive` and `Sold`; Twinfield returns
 * them in lowercase.
 */
enum ClassicFixedAssetStatus: string
{
    /** Created, but not depreciated yet: no purchase transaction is linked. */
    case ToBeActivated = 'tobeactivated';

    /** Depreciated every period. */
    case Active = 'active';

    /** Not depreciated anymore. */
    case Inactive = 'inactive';

    /** Sold or disposed of; see the sell date. */
    case Sold = 'sold';
}
