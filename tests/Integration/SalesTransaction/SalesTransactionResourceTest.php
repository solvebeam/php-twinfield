<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\SalesTransaction;

use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;
use SolveBeam\Twinfield\Transaction\OpenInvoice;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;

/**
 * Live sales transaction reads and open invoice searches (read-only) against TWINFIELD_COMPANY.
 *
 * Set TWINFIELD_SALES_TRANSACTION to an existing sales transaction, e.g. `VRK:202600012`, to
 * also read one: the Finder gives no transaction numbers.
 */
final class SalesTransactionResourceTest extends IntegrationTestCase
{
    public function testThrowsNotFoundForAnUnknownNumber(): void
    {
        $this->expectException(TransactionNotFoundException::class);

        $this->twinfield->salesTransactions()->get('VRK', 209999999);
    }

    public function testReadsASalesTransaction(): void
    {
        $setting = $this->environment->config->get('TWINFIELD_SALES_TRANSACTION');

        if ($setting === null) {
            self::markTestSkipped('Set TWINFIELD_SALES_TRANSACTION, e.g. VRK:202600012, to read a sales transaction.');
        }

        [$dayBook, $number] = explode(':', $setting) + [1 => ''];

        $transaction = $this->twinfield->salesTransactions()->get($dayBook, (int) $number);

        self::assertSame((int) $number, $transaction->number());
        self::assertNotSame('', $transaction->invoiceNumber);
        self::assertNotSame([], $transaction->details);

        // The total line balances with the detail and VAT lines.
        $lines = array_sum(array_map(static fn ($line): float => (float) $line->signedValue()->value, [
            ...$transaction->details,
            ...$transaction->vat,
        ]));
        self::assertEqualsWithDelta((float) $transaction->value()->value, $lines, 0.001);

        $input = $transaction->edit();
        self::assertSame($transaction->value()->value, $input->total()->value);
    }

    public function testSearchesOpenInvoices(): void
    {
        $page = $this->twinfield->salesTransactions()->openInvoices()->limit(20)->get();

        if ($page->items === []) {
            self::markTestSkipped('The company has no open invoices.');
        }

        self::assertContainsOnlyInstancesOf(OpenInvoice::class, $page->items);

        $customer = $page->items[0]->relation;
        $ofCustomer = $this->twinfield->salesTransactions()->openInvoices()->customer($customer)->get();

        self::assertNotSame([], $ofCustomer->items);

        foreach ($ofCustomer->items as $invoice) {
            self::assertSame($customer, $invoice->relation);
        }
    }
}
