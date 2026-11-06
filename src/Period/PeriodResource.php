<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Period;

use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\Command;
use SolveBeam\Twinfield\CommandQuery\Query;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Twinfield;

/**
 * Years and periods of the company, via the PeriodService (a Command & Query web service).
 *
 *   $periods = $twinfield->company('1000')->periods();
 *
 *   foreach ($periods->list(2025) as $period) {
 *       echo $period->number, ' ', $period->name, PHP_EOL;
 *   }
 *
 *   $periods->close(2025, 1);
 *
 * Errors are thrown as CommandQueryFaultException; `errorCode` holds a PeriodServiceFaultCode value.
 *
 * Field order in the commands follows the WSDL; the service does not accept another order.
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/periods
 */
final readonly class PeriodResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * All years of the company, e.g. `[2024, 2025]`.
     *
     * @return list<int>
     */
    public function years(): array
    {
        return $this->twinfield
            ->query('PeriodService', new Query('GetYears'), 'GetYearsResult')
            ->ints('Years');
    }

    /**
     * All periods of a year, including special periods such as 0 (opening balance).
     *
     * @return list<Period>
     */
    public function list(int $year): array
    {
        $result = $this->twinfield->query(
            'PeriodService',
            new Query('GetPeriods', ['Year' => $year]),
            'GetPeriodsResult',
        );

        return array_map(
            static fn (ResultElement $period): Period => new Period(
                number: $period->int('Number'),
                name: $period->string('Name'),
                endDate: $period->nullableDateTime('EndDate'),
                open: $period->bool('Open'),
            ),
            $result->list('Periods', 'Period'),
        );
    }

    /**
     * Creates a new year with the given number of periods, e.g. 12.
     */
    public function createYear(int $numberOfPeriods): void
    {
        if ($numberOfPeriods < 1) {
            throw new InvalidArgumentException('Number of periods must be at least 1.');
        }

        $this->process(new Command('CreateYear', ['NumberOfPeriods' => $numberOfPeriods]));
    }

    /**
     * Changes the end date of a period.
     */
    public function changeEndDate(int $year, int $period, DateTimeInterface $endDate): void
    {
        $this->process(new Command('ChangeEndDate', [
            'EndDate' => $endDate,
            'PeriodNumber' => $period,
            'Year' => $year,
        ]));
    }

    /**
     * Changes the name of a period.
     */
    public function changeName(int $year, int $period, string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Period name must not be empty.');
        }

        $this->process(new Command('ChangeName', [
            'Name' => $name,
            'PeriodNumber' => $period,
            'Year' => $year,
        ]));
    }

    /**
     * Opens a period, so transactions can be posted in it.
     */
    public function open(int $year, int $period): void
    {
        $this->process(new Command('OpenPeriod', ['PeriodNumber' => $period, 'Year' => $year]));
    }

    /**
     * Closes a period, so no transactions can be posted in it.
     */
    public function close(int $year, int $period): void
    {
        $this->process(new Command('ClosePeriod', ['PeriodNumber' => $period, 'Year' => $year]));
    }

    /**
     * Resets the years of the company: deletes all existing years and creates
     * `$newYear` as first year with `$newNumberOfPeriods` periods.
     */
    public function resetYears(int $newYear, int $newNumberOfPeriods): void
    {
        if ($newNumberOfPeriods < 1) {
            throw new InvalidArgumentException('Number of periods must be at least 1.');
        }

        $this->process(new Command('ResetYears', [
            'NewNumberOfPeriods' => $newNumberOfPeriods,
            'NewYear' => $newYear,
        ]));
    }

    /**
     * Deletes a year. Twinfield refuses this (`YearCanNotBeDeleted`) when the year cannot be deleted.
     */
    public function deleteYear(int $year): void
    {
        $this->process(new Command('DeleteYear', ['Year' => $year]));
    }

    private function process(Command $command): void
    {
        $this->twinfield->process('PeriodService', $command);
    }
}
