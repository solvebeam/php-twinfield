<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\Period;

use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Changes a period name and restores it. Does not create, delete or reset years.
 */
final class PeriodResourceTest extends WriteTestCase
{
    public function testChangesAndRestoresPeriodName(): void
    {
        $periods = $this->twinfield->periods();
        $years = $periods->years();

        if ($years === []) {
            self::markTestSkipped('The company has no years.');
        }

        $year = max($years);
        $original = $this->period($year, 1)->name;

        try {
            $periods->changeName($year, 1, $original . ' (test)');

            self::assertSame($original . ' (test)', $this->period($year, 1)->name);
        } finally {
            $periods->changeName($year, 1, $original);
        }

        self::assertSame($original, $this->period($year, 1)->name);
    }

    private function period(int $year, int $number): Period
    {
        foreach ($this->twinfield->periods()->list($year) as $period) {
            if ($period->number === $number) {
                return $period;
            }
        }

        self::fail("Period {$number} of {$year} not found.");
    }
}
