<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use SolveBeam\Twinfield\Period\Period;

/**
 * A period a budget can have lines for.
 */
final readonly class BudgetPeriod
{
    public function __construct(
        public Period $period,
        /** True for a system period such as 0 (opening balance) or 55 and up (closing). */
        public bool $isSystem,
    ) {
    }
}
