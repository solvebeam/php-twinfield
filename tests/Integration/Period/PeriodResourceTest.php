<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Period;

use SolveBeam\Twinfield\Period\PeriodServiceFaultCode;
use SolveBeam\Twinfield\Soap\SoapFaultException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live PeriodService queries (read-only) against TWINFIELD_COMPANY.
 */
final class PeriodResourceTest extends IntegrationTestCase
{
    public function testListsYears(): void
    {
        $years = $this->twinfield->periods()->years();

        self::assertNotEmpty($years);
        self::assertSame($years, array_values(array_unique($years)), 'Each year once.');
    }

    public function testListsPeriodsOfTheLastYear(): void
    {
        $years = $this->twinfield->periods()->years();

        if ($years === []) {
            self::markTestSkipped('The company has no years.');
        }

        $year = max($years);
        $periods = $this->twinfield->periods()->list($year);

        self::assertNotEmpty($periods);

        $numbers = array_map(static fn ($period): int => $period->number, $periods);
        $sorted = $numbers;
        sort($sorted);

        self::assertSame($sorted, $numbers, 'Periods are in order.');

        foreach ($periods as $period) {
            if ($period->endDate !== null) {
                self::assertSame($year, (int) $period->endDate->format('Y'), "Period {$period->number} ends in {$year}.");
            }
        }
    }

    public function testThrowsYearNotFound(): void
    {
        try {
            $this->twinfield->periods()->list(1901);
            self::fail('Expected a SoapFaultException.');
        } catch (SoapFaultException $exception) {
            self::assertSame(PeriodServiceFaultCode::YearNotFound->value, $exception->detailCode);
        }
    }
}
