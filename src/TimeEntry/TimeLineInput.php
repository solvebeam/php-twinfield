<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A time line (`<line type="TIME">`) to send; add it with `TimeEntryInput::time()`.
 */
final readonly class TimeLineInput
{
    public function __construct(
        /**
         * The duration as Twinfield takes it, a whole number from 1. The Twinfield documentation
         * calls it hours, but its own example fits minutes (`240` at a rate per 15 units).
         */
        public int $duration,
        public ?string $description = null,
        /** Null: Twinfield decides, from the project, activity or rate. */
        public ?bool $billable = null,
        /** Rate code, e.g. `T001`; null: Twinfield takes the default rate. */
        public ?string $rate = null,
    ) {
        if ($duration < 1) {
            throw new InvalidArgumentException(sprintf('Duration must be 1 or more, got %d.', $duration));
        }

        if ($rate !== null) {
            XmlValue::code($rate);
        }
    }
}
