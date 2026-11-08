<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Rate;

use SolveBeam\Twinfield\Rate\RateNotFoundException;
use SolveBeam\Twinfield\Rate\RateType;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live rate searches and reads (read-only) against TWINFIELD_COMPANY.
 */
final class RateResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsRates(): void
    {
        $rates = $this->twinfield->rates();
        $page = $rates->search()->limit(5)->get();

        if ($page->totalRows === 0) {
            self::markTestSkipped('The company has no rates.');
        }

        self::assertSame($page->totalRows, $rates->search()->count());

        foreach ($page as $summary) {
            usleep(300_000);
            $rate = $rates->get($summary->code);

            self::assertSame($summary->code, $rate->code);
            self::assertSame($summary->name, $rate->name);
            self::assertSame($this->twinfield->companyCode(), $rate->company);
            self::assertNotEmpty($rate->prices);

            // edit() must give an input that Twinfield accepts; here only that it builds.
            self::assertStringContainsString('<ratechange id=', $rate->edit()->toXml('X')->toXml());
        }
    }

    public function testFiltersOnRateType(): void
    {
        $rates = $this->twinfield->rates();

        self::assertSame(
            $rates->search()->count(),
            $rates->search()->rateType(RateType::Time)->count()
                + $rates->search()->rateType(RateType::Quantity)->count(),
        );
    }

    public function testThrowsNotFound(): void
    {
        $this->expectException(RateNotFoundException::class);

        $this->twinfield->rates()->get('SDKMISSING9');
    }
}
