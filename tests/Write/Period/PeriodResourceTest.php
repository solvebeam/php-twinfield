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

        $period = Period::of(max($years), 1);
        $original = $periods->get($period)->name;

        if ($original === null) {
            self::markTestSkipped("Period {$period} has no name to restore.");
        }

        try {
            $periods->changeName($period, $original . ' (test)');

            self::assertSame($original . ' (test)', $periods->get($period)->name);
        } finally {
            $periods->changeName($period, $original);
        }

        self::assertSame($original, $periods->get($period)->name);
    }
}
