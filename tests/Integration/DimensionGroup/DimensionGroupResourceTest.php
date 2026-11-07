<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\DimensionGroup;

use SolveBeam\Twinfield\DimensionGroup\DimensionGroupNotFoundException;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupSummary;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live dimension group searches (read-only) against TWINFIELD_COMPANY.
 *
 * Reading a group sends a `<dimensiongroup>` request, which the write guard blocks, so reads
 * are in tests/Write/DimensionGroup.
 */
final class DimensionGroupResourceTest extends IntegrationTestCase
{
    public function testSearchesDimensionGroups(): void
    {
        $groups = $this->twinfield->dimensionGroups();
        $page = $groups->search()->limit(5)->get();

        self::assertContainsOnlyInstancesOf(DimensionGroupSummary::class, $page->items);
        self::assertSame($page->totalRows, $groups->search()->count());

        $first = $page->items[0] ?? null;

        if ($first === null) {
            self::markTestSkipped('The company has no dimension groups.');
        }

        self::assertTrue($groups->exists($first->code));
    }

    public function testSearchesByDimensionType(): void
    {
        $all = $this->twinfield->dimensionGroups()->search()->count();
        $customers = $this->twinfield->dimensionGroups()->search()->dimensionType('DEB')->count();

        self::assertLessThanOrEqual($all, $customers);
    }

    public function testUnknownGroupIsNotFoundWithoutCreatingIt(): void
    {
        $groups = $this->twinfield->dimensionGroups();
        $code = 'ZZ-UNKNOWN-' . random_int(10000, 99999);

        self::assertFalse($groups->exists($code));
        self::assertNull($groups->find($code), 'Only the Finder is asked: the write guard would block a read.');

        $this->expectException(DimensionGroupNotFoundException::class);

        $groups->get($code);
    }
}
