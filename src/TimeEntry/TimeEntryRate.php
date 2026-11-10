<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

use InvalidArgumentException;
use SolveBeam\Twinfield\Rate\UnitPrice;

/**
 * The rate of a time entry line as Twinfield returns it: the code with its name, the internal and
 * external price and the units the prices are for, e.g. rate `T001` at 15.00 per 15 units.
 */
final readonly class TimeEntryRate
{
    /**
     * @param numeric-string $units
     */
    public function __construct(
        /** Rate code, e.g. `T001`. */
        public string $code,
        public string $name,
        public string $shortName,
        /** Internal (cost) price per `units`. */
        public UnitPrice $internalPrice,
        /** External (sales) price per `units`. */
        public UnitPrice $externalPrice,
        /** What the prices are for, e.g. `15.00` for a price per 15 of the duration, or `1` per quantity. */
        public string $units,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('Rate code must not be empty.');
        }
    }
}
