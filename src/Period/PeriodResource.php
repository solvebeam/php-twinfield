<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Period;

use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\Command;
use SolveBeam\Twinfield\CommandQuery\Query;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Twinfield;

/**
 * Years and periods of the company, via the PeriodService (a Command & Query web service).
 *
 *   $periods = $twinfield->company('1000')->periods();
 *
 *   foreach ($periods->list(2025) as $details) {
 *       echo $details->period, ' ', $details->name ?? '-', PHP_EOL;
 *   }
 *
 *   $periods->get($transaction->header->period)->open;
 *   $periods->close(Period::of(2025, 1));
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
     * @return list<PeriodDetails>
     */
    public function list(int $year): array
    {
        $result = $this->twinfield->query(
            'PeriodService',
            new Query('GetPeriods', ['Year' => $year]),
            'GetPeriodsResult',
        );

        $company = $this->twinfield->companyReference();

        return array_map(
            static fn (ResultElement $period): PeriodDetails => new PeriodDetails(
                company: $company,
                // Twinfield does not return the year per period.
                period: Period::of($year, $period->int('Number')),
                name: $period->nullableString('Name'),
                endDate: $period->nullableDateTime('EndDate'),
                open: $period->bool('Open'),
            ),
            $result->list('Periods', 'Period'),
        );
    }

    /**
     * Name, end date and status of one period, e.g. the period of a transaction.
     *
     * Reads all periods of the year: use `list()` for many periods of the same year.
     *
     * @throws PeriodNotFoundException When the year does not exist, or has no period with this number.
     */
    public function get(Period $period): PeriodDetails
    {
        try {
            $periods = $this->list($period->year);
        } catch (CommandQueryFaultException $exception) {
            if (PeriodServiceFaultCode::tryFrom($exception->errorCode) === PeriodServiceFaultCode::YearNotFound) {
                throw new PeriodNotFoundException($period, $exception);
            }

            throw $exception;
        }

        foreach ($periods as $details) {
            if ($details->period->equals($period)) {
                return $details;
            }
        }

        throw new PeriodNotFoundException($period);
    }

    /**
     * The period, or null when the year does not exist or has no period with this number.
     */
    public function find(Period $period): ?PeriodDetails
    {
        try {
            return $this->get($period);
        } catch (PeriodNotFoundException) {
            return null;
        }
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
    public function changeEndDate(Period $period, DateTimeInterface $endDate): void
    {
        $this->process(new Command('ChangeEndDate', [
            'EndDate' => $endDate,
            'PeriodNumber' => $period->number,
            'Year' => $period->year,
        ]));
    }

    /**
     * Changes the name of a period.
     */
    public function changeName(Period $period, string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Period name must not be empty.');
        }

        $this->process(new Command('ChangeName', [
            'Name' => $name,
            'PeriodNumber' => $period->number,
            'Year' => $period->year,
        ]));
    }

    /**
     * Opens a period, so transactions can be posted in it.
     */
    public function open(Period $period): void
    {
        $this->process(new Command('OpenPeriod', ['PeriodNumber' => $period->number, 'Year' => $period->year]));
    }

    /**
     * Closes a period, so no transactions can be posted in it.
     */
    public function close(Period $period): void
    {
        $this->process(new Command('ClosePeriod', ['PeriodNumber' => $period->number, 'Year' => $period->year]));
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
