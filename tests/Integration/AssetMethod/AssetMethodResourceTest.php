<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\AssetMethod;

use SolveBeam\Twinfield\AssetMethod\AssetMethodNotFoundException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live asset method searches and reads (read-only) against TWINFIELD_COMPANY.
 */
final class AssetMethodResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsAssetMethods(): void
    {
        $assetMethods = $this->twinfield->assetMethods();
        $page = $assetMethods->search()->limit(5)->get();

        if ($page->totalRows === 0) {
            self::markTestSkipped('The company has no asset methods.');
        }

        self::assertSame($page->totalRows, $assetMethods->search()->count());

        foreach ($page as $summary) {
            $method = $assetMethods->get($summary->code);

            self::assertSame($summary->code, $method->code);
            self::assertSame($summary->name, $method->name);
            self::assertSame($this->twinfield->companyCode(), $method->company);
            self::assertCount(5, $method->freeTexts);

            // edit() must give an input that Twinfield accepts; here only that it builds.
            self::assertSame($method->code, $method->edit()->code);
        }
    }

    public function testThrowsNotFound(): void
    {
        $this->expectException(AssetMethodNotFoundException::class);

        $this->twinfield->assetMethods()->get('SDKMISSING9');
    }
}
