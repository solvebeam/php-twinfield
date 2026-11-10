<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\FixedAsset;

use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live REST API fixed assets of the new fixed assets module (read-only) of TWINFIELD_COMPANY.
 */
final class FixedAssetResourceTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireScopes(Scope::ReadFixedAssets);
    }

    public function testReadsFixedAssetsAndTheirHistory(): void
    {
        $summaries = iterator_to_array($this->twinfield->fixedAssets()->search()->all(), false);

        if ($summaries === []) {
            self::markTestSkipped('The company has no fixed assets in the new fixed assets module.');
        }

        foreach (array_slice($summaries, 0, 5) as $summary) {
            $asset = $this->twinfield->fixedAssets()->get($summary->id);

            self::assertSame($summary->code, $asset->code);
            self::assertSame($summary->status, $asset->status);
            self::assertTrue($summary->netBookValue->equals($asset->netBookValue));
            self::assertNotEmpty($asset->depreciationSettings);

            foreach ($this->twinfield->fixedAssets()->actions($asset->id) as $action) {
                self::assertNotSame('', $action->user);
            }
        }
    }

    public function testReadsClassesAndGroups(): void
    {
        $classes = $this->twinfield->fixedAssets()->classes()->get();

        foreach (array_slice($classes->items, 0, 3) as $summary) {
            $class = $this->twinfield->fixedAssets()->getClass($summary->id);

            self::assertSame($summary->name, $class->name);
            self::assertNotEmpty($class->depreciationSettings);
        }

        foreach ($this->twinfield->fixedAssets()->groups()->all() as $summary) {
            self::assertSame($summary->name, $this->twinfield->fixedAssets()->getGroup($summary->id)->name);
        }

        self::assertGreaterThanOrEqual(count($classes->items), $classes->filteredTotal);
    }
}
