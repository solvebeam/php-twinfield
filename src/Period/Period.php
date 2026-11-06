<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Period;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A period of a financial year, as returned by the PeriodService.
 */
final readonly class Period
{
    public function __construct(
        /** Period number: 1–12 for a year with 12 periods, plus special periods such as 0 (opening balance). */
        public int $number,
        /** Name in the language of the company, e.g. `januari 2025`. */
        public string $name,
        /**
         * Last day of the period. Twinfield returns no end date (nil) for special periods,
         * such as 0 (opening balance) and 55 (preceding journal entries).
         */
        public ?DateTimeImmutable $endDate,
        public bool $open,
    ) {
        if ($number < 0) {
            throw new InvalidArgumentException('Period number must be 0 or more.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('Period name must not be empty.');
        }
    }
}
