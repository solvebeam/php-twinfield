<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A quantity line of a saved time entry, e.g. kilometres.
 */
final readonly class QuantityLine
{
    /**
     * @param numeric-string $quantity
     */
    public function __construct(
        /** Line id within the time entry, from 1. */
        public int $id,
        /** The number of units, e.g. `1.5`. */
        public string $quantity,
        /** Internal (cost) value of the quantity. */
        public Amount $internalValue,
        /** External (sales) value of the quantity. */
        public Amount $externalValue,
        public bool $billable,
        public TimeEntryRate $rate,
        /** Currency code of the values, e.g. `EUR`. */
        public string $currency,
    ) {
    }
}
