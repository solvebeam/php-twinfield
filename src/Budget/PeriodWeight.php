<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use InvalidArgumentException;

/**
 * The weight of a period in a spreading template: an amount is spread over the periods in proportion
 * to their weights, e.g. weights 1, 1, 2 give 25%, 25% and 50%.
 */
final readonly class PeriodWeight
{
    public function __construct(
        /** Period number, e.g. `1`. */
        public int $period,
        public int $weight,
    ) {
        if ($period < 0 || $weight < 0) {
            throw new InvalidArgumentException('Period and weight must be 0 or more.');
        }
    }
}
