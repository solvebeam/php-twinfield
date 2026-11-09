<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Activity;

use SolveBeam\Twinfield\Activity\ActivitySummary;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live activity searches and reads (read-only) against TWINFIELD_COMPANY.
 */
final class ActivityResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsActivities(): void
    {
        $activities = $this->twinfield->activities();
        $page = $activities->search()->limit(5)->get();

        if ($page->totalRows === 0) {
            self::markTestSkipped('The company has no activities.');
        }

        self::assertContainsOnlyInstancesOf(ActivitySummary::class, $page->items);
        self::assertSame($page->totalRows, $activities->search()->count());

        $summary = $page->items[0];
        $activity = $activities->get($summary->code);

        self::assertSame($summary->code, $activity->code);
        self::assertSame($summary->name, $activity->name);
        self::assertSame($this->environment->config->company(), $activity->company->code);
        self::assertNotSame('', $activity->uid);
    }

    public function testSearchesByCode(): void
    {
        $activities = $this->twinfield->activities();
        $first = $activities->search()->limit(1)->get()->items[0] ?? null;

        if ($first === null) {
            self::markTestSkipped('The company has no activities.');
        }

        $codes = array_map(
            static fn (ActivitySummary $activity): string => $activity->code,
            iterator_to_array($activities->search($first->code)->inCode()->all(), false),
        );

        self::assertSame([$first->code], $codes);
    }

    public function testReadsEveryActivity(): void
    {
        $count = 0;

        foreach ($this->twinfield->activities()->search()->limit(50)->all() as $summary) {
            self::assertSame($summary->code, $this->twinfield->activities()->get($summary->code)->code);

            if (++$count === 10) {
                break;
            }
        }

        if ($count === 0) {
            self::markTestSkipped('The company has no activities.');
        }
    }

    public function testUnknownActivityIsNotFound(): void
    {
        $activities = $this->twinfield->activities();
        $code = $this->unusedCode();

        self::assertNull($activities->find($code));

        $this->expectException(DimensionNotFoundException::class);

        $activities->get($code);
    }

    private function unusedCode(): string
    {
        for ($number = 999; $number > 900; $number--) {
            $code = sprintf('A%03d', $number);

            // find(), not search(): deleted activities keep their code.
            if ($this->twinfield->activities()->find($code) === null) {
                return $code;
            }
        }

        self::markTestSkipped('No unused activity code A901–A999.');
    }
}
