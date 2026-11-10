<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Supplier;

use SolveBeam\Twinfield\Supplier\SupplierSummary;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live supplier searches and reads (read-only) against TWINFIELD_COMPANY.
 */
final class SupplierResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsSuppliers(): void
    {
        $suppliers = $this->twinfield->suppliers();
        $page = $suppliers->search()->limit(5)->get();

        if ($page->totalRows === 0) {
            self::markTestSkipped('The company has no suppliers.');
        }

        self::assertSame($page->totalRows, $suppliers->search()->count());

        $summary = $page->items[0];
        $supplier = $suppliers->get($summary->code);

        self::assertSame($summary->code, $supplier->code);
        self::assertSame($summary->name, $supplier->name);
        self::assertSame($this->environment->config->company(), $supplier->company->code);
        self::assertNotSame('', $supplier->uid);
        self::assertNotNull($supplier->modified, 'A read response has <modified>.');
        self::assertNotSame('', $supplier->financials->balanceAccount);
    }

    public function testReadsEverySupplierAndEditKeepsItsValues(): void
    {
        $count = 0;

        foreach ($this->twinfield->suppliers()->search()->limit(50)->all() as $summary) {
            $supplier = $this->twinfield->suppliers()->get($summary->code);
            $input = $supplier->edit();

            self::assertSame($supplier->code, $input->code);
            self::assertSame((string) $supplier->financials->dueDays, $input->value('financials/duedays'));
            self::assertCount(count($supplier->addresses), $input->addresses ?? []);
            self::assertCount(count($supplier->banks), $input->banks ?? []);
            self::assertSame($supplier->financials->meansOfPayment->value, $input->value('financials/meansofpayment'));
            self::assertSame($supplier->financials->relationsReference, $input->value('financials/relationsreference'));

            if (++$count === 10) {
                break;
            }
        }

        if ($count === 0) {
            self::markTestSkipped('The company has no suppliers.');
        }
    }

    public function testSearchesByCode(): void
    {
        $suppliers = $this->twinfield->suppliers();
        $first = $suppliers->search()->limit(1)->get()->items[0] ?? null;

        if ($first === null) {
            self::markTestSkipped('The company has no suppliers.');
        }

        $codes = array_map(
            static fn (SupplierSummary $supplier): string => $supplier->code,
            iterator_to_array($suppliers->search($first->code)->inCode()->all(), false),
        );

        self::assertSame([$first->code], $codes);
    }

    public function testSearchesInAddresses(): void
    {
        $suppliers = $this->twinfield->suppliers();

        foreach ($suppliers->search()->limit(20)->all() as $summary) {
            $city = $suppliers->get($summary->code)->defaultAddress()?->city;

            if ($city === null) {
                continue;
            }

            $codes = array_map(
                static fn (SupplierSummary $supplier): string => $supplier->code,
                iterator_to_array($suppliers->search('*' . $city . '*')->inAddress()->all(), false),
            );

            self::assertContains($summary->code, $codes);

            return;
        }

        self::markTestSkipped('No supplier with a city in its default address.');
    }

    public function testUnknownSupplierIsNotFound(): void
    {
        $suppliers = $this->twinfield->suppliers();

        self::assertNull($suppliers->find('ZZZ999'));

        $this->expectException(DimensionNotFoundException::class);

        $suppliers->get('ZZZ999');
    }
}
