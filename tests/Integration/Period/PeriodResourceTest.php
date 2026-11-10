<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Period;

use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Period\PeriodDetails;
use SolveBeam\Twinfield\Period\PeriodNotFoundException;
use SolveBeam\Twinfield\Period\PeriodServiceFaultCode;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
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

        $numbers = array_map(static fn (PeriodDetails $details): int => $details->period->number, $periods);
        $sorted = $numbers;
        sort($sorted);

        self::assertSame($sorted, $numbers, 'Periods are in order.');

        // A financial year does not have to follow the calendar year, so only check that end dates go up.
        $previous = null;

        foreach ($periods as $details) {
            self::assertSame($year, $details->period->year);

            if ($details->endDate === null) {
                continue;
            }

            if ($previous !== null) {
                self::assertGreaterThan(
                    $previous,
                    $details->endDate,
                    "Period {$details->period} ends after the previous period.",
                );
            }

            $previous = $details->endDate;
        }
    }

    public function testGetsTheFirstPeriodOfTheLastYear(): void
    {
        $years = $this->twinfield->periods()->years();

        if ($years === []) {
            self::markTestSkipped('The company has no years.');
        }

        $period = Period::of(max($years), 1);

        self::assertTrue($this->twinfield->periods()->get($period)->period->equals($period));
    }

    public function testThrowsPeriodNotFoundForAYearThatDoesNotExist(): void
    {
        $this->expectException(PeriodNotFoundException::class);

        $this->twinfield->periods()->get(Period::of(1901, 1));
    }

    public function testThrowsYearNotFound(): void
    {
        try {
            $this->twinfield->periods()->list(1901);
            self::fail('Expected a CommandQueryFaultException.');
        } catch (CommandQueryFaultException $exception) {
            self::assertSame('PeriodServiceFault', $exception->type);
            self::assertSame(PeriodServiceFaultCode::YearNotFound->value, $exception->errorCode);
        }
    }
}
