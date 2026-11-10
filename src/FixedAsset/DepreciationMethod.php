<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * How a fixed asset is depreciated.
 */
enum DepreciationMethod: string
{
    case StraightLine = 'straightLine';
    case ReducingBalance = 'reducingBalance';
    case NoDepreciation = 'noDepreciation';
    case Unknown = 'unknown';
    case StraightLineExclRemainderValue = 'straightLineExclRemainderValue';
    case ReducingBalanceExclRemainderValue = 'reducingBalanceExclRemainderValue';
    case FixedAmountPerPeriod = 'fixedAmountPerPeriod';
}
