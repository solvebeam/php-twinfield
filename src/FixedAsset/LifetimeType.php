<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * How the lifetime of a depreciation method is expressed.
 */
enum LifetimeType: string
{
    /** Number of periods. */
    case Linear = 'linear';
    /** Percentage per year. */
    case LinearPercents = 'linearPercents';
    /** Number of years. */
    case LinearPerYear = 'linearPerYear';
    /** Amount per period. */
    case FixedAmountPerPeriod = 'fixedAmountPerPeriod';
}
