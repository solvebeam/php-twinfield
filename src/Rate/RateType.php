<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rate;

/**
 * What a rate is for (`projectrate/type`): hours, or quantities such as kilometres.
 */
enum RateType: string
{
    /** Rate for hours in a timesheet. */
    case Time = 'time';

    /** Rate for quantities such as kilometres. */
    case Quantity = 'quantity';
}
