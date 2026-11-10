<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A time line of a saved time entry.
 */
final readonly class TimeLine
{
    public function __construct(
        /** Line id within the time entry, from 1. */
        public int $id,
        /** The duration as entered; see `TimeLineInput::$duration` for the unit. */
        public int $duration,
        /** Internal (cost) value of the duration. */
        public Amount $internalValue,
        /** External (sales) value of the duration. */
        public Amount $externalValue,
        /** Null when no description was entered. */
        public ?string $description,
        public bool $billable,
        public TimeEntryRate $rate,
        /** Currency code of the values, e.g. `EUR`. */
        public string $currency,
    ) {
    }
}
