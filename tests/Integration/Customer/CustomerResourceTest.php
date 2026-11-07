<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Customer;

use SolveBeam\Twinfield\Customer\CustomerSummary;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live customer searches and reads (read-only) against TWINFIELD_COMPANY.
 */
final class CustomerResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsCustomers(): void
    {
        $customers = $this->twinfield->customers();
        $page = $customers->search()->limit(5)->get();

        if ($page->totalRows === 0) {
            self::markTestSkipped('The company has no customers.');
        }

        self::assertContainsOnlyInstancesOf(CustomerSummary::class, $page->items);
        self::assertSame($page->totalRows, $customers->search()->count());

        $summary = $page->items[0];
        $customer = $customers->get($summary->code);

        self::assertSame($summary->code, $customer->code);
        self::assertSame($summary->name, $customer->name);
        self::assertSame($this->environment->config->company(), $customer->company);
        self::assertNotSame('', $customer->uid);
        self::assertNotNull($customer->modified, 'A read response has <modified>.');
        self::assertNotSame('', $customer->financials->balanceAccount);
    }

    public function testReadsEveryCustomerAndEditKeepsItsValues(): void
    {
        $count = 0;

        foreach ($this->twinfield->customers()->search()->limit(50)->all() as $summary) {
            $customer = $this->twinfield->customers()->get($summary->code);
            $input = $customer->edit();

            self::assertSame($customer->code, $input->code);
            self::assertSame((string) $customer->financials->dueDays, $input->value('financials/duedays'));
            self::assertCount(count($customer->addresses), $input->addresses ?? []);
            self::assertCount(count($customer->banks), $input->banks ?? []);

            if (++$count === 10) {
                break;
            }
        }

        if ($count === 0) {
            self::markTestSkipped('The company has no customers.');
        }
    }

    public function testSearchesByCode(): void
    {
        $customers = $this->twinfield->customers();
        $first = $customers->search()->limit(1)->get()->items[0] ?? null;

        if ($first === null) {
            self::markTestSkipped('The company has no customers.');
        }

        $codes = array_map(
            static fn (CustomerSummary $customer): string => $customer->code,
            iterator_to_array($customers->search($first->code)->inCode()->all(), false),
        );

        self::assertSame([$first->code], $codes);
    }

    public function testSearchesInAddresses(): void
    {
        $customers = $this->twinfield->customers();

        foreach ($customers->search()->limit(20)->all() as $summary) {
            $city = $customers->get($summary->code)->defaultAddress()?->city;

            if ($city === null) {
                continue;
            }

            $codes = array_map(
                static fn (CustomerSummary $customer): string => $customer->code,
                iterator_to_array($customers->search('*' . $city . '*')->inAddress()->all(), false),
            );

            self::assertContains($summary->code, $codes);

            return;
        }

        self::markTestSkipped('No customer with a city in its default address.');
    }

    public function testUnknownCustomerIsNotFound(): void
    {
        $customers = $this->twinfield->customers();

        self::assertNull($customers->find('ZZZ999'));

        $this->expectException(DimensionNotFoundException::class);

        $customers->get('ZZZ999');
    }
}
