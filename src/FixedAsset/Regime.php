<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * Depreciation regime: an asset can be depreciated differently for tax (fiscal), the annual accounts
 * (commercial) and management (economic).
 */
enum Regime: string
{
    /** The same for all regimes. */
    case Generic = 'generic';
    case Fiscal = 'fiscal';
    case Commercial = 'commercial';
    case Economic = 'economic';
}
