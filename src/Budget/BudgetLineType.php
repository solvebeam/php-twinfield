<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

/**
 * Filter of `BudgetLineSearch::type()`: money, quantities or both.
 */
enum BudgetLineType: string
{
    case All = 'all';
    case Monetary = 'monetary';
    case Quantity = 'quantity';
}
