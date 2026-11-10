<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Currency;

use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live currency searches (read-only) against TWINFIELD_COMPANY.
 *
 * Reading a currency sends a `<currency>` request, which the write guard treats as a write
 * (Twinfield has no `<read>` for currencies), so get() is covered by the write tests.
 */
final class CurrencyResourceTest extends IntegrationTestCase
{
    public function testSearchesCurrencies(): void
    {
        $currencies = $this->twinfield->currencies();
        $page = $currencies->search()->limit(5)->get();

        self::assertGreaterThan(0, $page->totalRows, 'Every company has at least its base currency.');
        self::assertSame($page->totalRows, $currencies->search()->count());
    }

    public function testChecksWhetherACodeExists(): void
    {
        $currencies = $this->twinfield->currencies();
        $first = $currencies->search()->limit(1)->get()->items[0];

        self::assertTrue($currencies->exists($first->code));
        self::assertFalse($currencies->exists($first->code . 'X'));
    }
}
