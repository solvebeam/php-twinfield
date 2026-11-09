<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\VatCode;

use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;
use SolveBeam\Twinfield\VatCode\VatCodeNotFoundException;
use SolveBeam\Twinfield\VatCode\VatCodeType;

/**
 * Live VAT code searches and reads (read-only) against TWINFIELD_COMPANY.
 */
final class VatCodeResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsVatCodes(): void
    {
        $vatCodes = $this->twinfield->vatCodes();
        $page = $vatCodes->search()->limit(10)->get();

        if ($page->totalRows === 0) {
            self::markTestSkipped('The company has no VAT codes.');
        }

        self::assertSame($page->totalRows, $vatCodes->search()->count());

        foreach ($page as $summary) {
            usleep(300_000);
            $vatCode = $vatCodes->get($summary->code);

            self::assertSame($summary->code, $vatCode->code);
            self::assertSame($summary->name, $vatCode->name);
            self::assertSame($this->twinfield->companyCode(), $vatCode->company);
            self::assertNotEmpty($vatCode->percentages);

            // edit() must give an input that Twinfield accepts; here only that it builds.
            self::assertStringContainsString('<percentages><percentage>', $vatCode->edit()->toXml('X')->toXml());
        }
    }

    public function testFiltersOnVatType(): void
    {
        $vatCodes = $this->twinfield->vatCodes();

        self::assertSame(
            $vatCodes->search()->count(),
            $vatCodes->search()->vatType(VatCodeType::Sales)->count()
                + $vatCodes->search()->vatType(VatCodeType::Purchase)->count(),
        );
    }

    public function testThrowsNotFound(): void
    {
        $this->expectException(VatCodeNotFoundException::class);

        $this->twinfield->vatCodes()->get('SDKMISSING9');
    }
}
