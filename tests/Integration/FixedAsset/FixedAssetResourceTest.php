<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\FixedAsset;

use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\FixedAsset\FixedAssetField;
use SolveBeam\Twinfield\FixedAsset\FixedAssetSummary;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live fixed asset searches and reads (read-only) against TWINFIELD_COMPANY. Skipped when the
 * company has no fixed assets; test company 1002_3 has some.
 */
final class FixedAssetResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsFixedAssets(): void
    {
        $fixedAssets = $this->twinfield->fixedAssets();
        $page = $fixedAssets->search()->limit(5)->get();

        if ($page->totalRows === 0) {
            self::markTestSkipped('The company has no fixed assets.');
        }

        self::assertContainsOnlyInstancesOf(FixedAssetSummary::class, $page->items);
        self::assertSame($page->totalRows, $fixedAssets->search()->count());

        $summary = $page->items[0];
        $asset = $fixedAssets->get($summary->code);

        self::assertSame($summary->code, $asset->code);
        self::assertSame($summary->name, $asset->name);
        self::assertSame($this->environment->config->company(), $asset->company);
        self::assertNotSame('', $asset->uid);
        self::assertNotNull($asset->modified, 'A read response has <modified>.');
        self::assertSame('KPL', $asset->costCenter->type);
        self::assertTrue($asset->isLocked(FixedAssetField::Status), 'Twinfield always locks the status.');
        self::assertTrue($asset->isLocked(FixedAssetField::SellDate), 'Twinfield always locks the sell date.');
    }

    public function testReadsEveryFixedAsset(): void
    {
        $count = 0;

        foreach ($this->twinfield->fixedAssets()->search()->limit(50)->all() as $summary) {
            $asset = $this->twinfield->fixedAssets()->get($summary->code);

            self::assertSame($summary->code, $asset->code);

            // edit() must give an input that only has settings that may be changed.
            foreach ($asset->lockedFields as $field) {
                self::assertFalse($asset->edit()->has('fixedassets/' . $field->value), $field->value . ' is locked.');
            }

            if (++$count === 10) {
                break;
            }
        }

        if ($count === 0) {
            self::markTestSkipped('The company has no fixed assets.');
        }
    }

    public function testSearchesByCode(): void
    {
        $fixedAssets = $this->twinfield->fixedAssets();
        $first = $fixedAssets->search()->limit(1)->get()->items[0] ?? null;

        if ($first === null) {
            self::markTestSkipped('The company has no fixed assets.');
        }

        $codes = array_map(
            static fn (FixedAssetSummary $asset): string => $asset->code,
            iterator_to_array($fixedAssets->search($first->code)->inCode()->all(), false),
        );

        self::assertSame([$first->code], $codes);
    }

    public function testUnknownFixedAssetIsNotFound(): void
    {
        $fixedAssets = $this->twinfield->fixedAssets();

        self::assertNull($fixedAssets->find('ZZZZ999'));

        $this->expectException(DimensionNotFoundException::class);

        $fixedAssets->get('ZZZZ999');
    }
}
