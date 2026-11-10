<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

/**
 * What a budget line holds: money or one of the two quantities.
 */
enum UnitOfMeasure: string
{
    case Monetary = 'monetary';
    case Quantity1 = 'qty1';
    case Quantity2 = 'qty2';
}
