<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A budget with the balance of one year (`budgets()->annual()`).
 */
final readonly class AnnualBudget
{
    public function __construct(
        public Budget $budget,
        public int $year,
        /** Balance of all monetary lines of the year: debit minus credit. */
        public Amount $balance,
    ) {
    }
}
