<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

use DateTimeImmutable;
use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A time entry as Twinfield returns it after saving: the time and quantities of one user on one
 * date, with the names, rates and values Twinfield adds.
 */
final readonly class TimeEntry
{
    /**
     * @param list<TimeLine>     $timeLines
     * @param list<QuantityLine> $quantityLines
     */
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        public TimeEntryType $type,
        /** The user the time is entered for. */
        public NamedReference $user,
        public DateTimeImmutable $date,
        /** The project (`prj1`); null when the entry has none. */
        public ?NamedReference $project,
        /** The activity (`prj2`); null when the entry has none. */
        public ?NamedReference $activity,
        /** Code of the customer of the project (`accountsreceivable`); null when Twinfield leaves it out. */
        public ?string $customer,
        /** The user who authorises the entry; null when Twinfield leaves it out. */
        public ?NamedReference $authoriser,
        public array $timeLines,
        public array $quantityLines,
    ) {
    }
}
