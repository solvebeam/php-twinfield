<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\CostCenter;

use SolveBeam\Twinfield\CostCenter\CostCenterSummary;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live cost center searches and reads (read-only) against TWINFIELD_COMPANY.
 */
final class CostCenterResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsCostCenters(): void
    {
        $costCenters = $this->twinfield->costCenters();
        $page = $costCenters->search()->limit(5)->get();

        if ($page->totalRows === 0) {
            self::markTestSkipped('The company has no cost centers.');
        }

        self::assertContainsOnlyInstancesOf(CostCenterSummary::class, $page->items);
        self::assertSame($page->totalRows, $costCenters->search()->count());

        $summary = $page->items[0];
        $costCenter = $costCenters->get($summary->code);

        self::assertSame($summary->code, $costCenter->code);
        self::assertSame($summary->name, $costCenter->name);
        self::assertSame($this->environment->config->company(), $costCenter->company);
        self::assertNotSame('', $costCenter->uid);
        self::assertNotNull($costCenter->modified, 'A read response has <modified>.');
    }

    public function testSearchesByCode(): void
    {
        $costCenters = $this->twinfield->costCenters();
        $first = $costCenters->search()->limit(1)->get()->items[0] ?? null;

        if ($first === null) {
            self::markTestSkipped('The company has no cost centers.');
        }

        $codes = array_map(
            static fn (CostCenterSummary $costCenter): string => $costCenter->code,
            iterator_to_array($costCenters->search($first->code)->inCode()->all(), false),
        );

        self::assertSame([$first->code], $codes);
    }

    public function testReadsEveryCostCenter(): void
    {
        $count = 0;

        foreach ($this->twinfield->costCenters()->search()->limit(50)->all() as $summary) {
            self::assertSame($summary->code, $this->twinfield->costCenters()->get($summary->code)->code);

            if (++$count === 10) {
                break;
            }
        }

        if ($count === 0) {
            self::markTestSkipped('The company has no cost centers.');
        }
    }

    public function testUnknownCostCenterIsNotFound(): void
    {
        $costCenters = $this->twinfield->costCenters();
        $code = Example::unusedCostCenterCode($this->twinfield);

        self::assertNull($costCenters->find($code));

        $this->expectException(DimensionNotFoundException::class);

        $costCenters->get($code);
    }
}
