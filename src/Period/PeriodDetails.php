<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Period;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A period of a financial year with its name, end date and status, as returned by the PeriodService.
 *
 *   $details = $twinfield->periods()->get(Period::of(2025, 1));
 *
 *   echo $details->period, ' ', $details->name ?? '-', ' ', $details->open ? 'open' : 'closed', PHP_EOL;
 */
final readonly class PeriodDetails
{
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        /**
         * Year and period number, e.g. `2025/01`. Numbers are 1–12 for a year with 12 periods,
         * plus special periods such as 0 (opening balance) and 55 (preceding journal entries).
         */
        public Period $period,
        /**
         * Name in the language of the company, e.g. `januari 2025`. Null when the period has no
         * name: Twinfield returns an empty `<Name/>` for all periods of some (often older) years.
         */
        public ?string $name,
        /**
         * Last day of the period. Twinfield returns no end date (nil) for special periods,
         * such as 0 (opening balance) and 55 (preceding journal entries).
         */
        public ?DateTimeImmutable $endDate,
        /** Whether transactions can be posted in the period. */
        public bool $open,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Period name must not be empty; use null for a period without name.');
        }
    }
}
