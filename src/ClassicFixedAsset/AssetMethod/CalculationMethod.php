<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod;

/**
 * How assets with this method are depreciated (`calcmethod`).
 */
enum CalculationMethod: string
{
    /** No depreciation. */
    case None = 'none';

    /** The same amount every period, over a number of periods. */
    case Linear = 'linear';

    /** The same amount every period: a percentage of the purchase value per year. */
    case LinearPercentage = 'linearpercentage';

    /** A percentage of the remaining book value per year. */
    case ReducingBalance = 'reducebalance';
}
