<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\PurchaseTransaction;

use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\OpenInvoice;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;

/**
 * Live purchase transaction reads and open invoice searches (read-only) against TWINFIELD_COMPANY.
 *
 * Set TWINFIELD_PURCHASE_TRANSACTION to an existing purchase transaction, e.g. `INK:202600021`,
 * to also read one: the Finder gives no transaction numbers.
 */
final class PurchaseTransactionResourceTest extends IntegrationTestCase
{
    public function testThrowsNotFoundForAnUnknownNumber(): void
    {
        $this->expectException(TransactionNotFoundException::class);

        $this->twinfield->purchaseTransactions()->get('INK', 209999999);
    }

    public function testReadsAPurchaseTransaction(): void
    {
        $setting = $this->environment->config->get('TWINFIELD_PURCHASE_TRANSACTION');

        if ($setting === null) {
            self::markTestSkipped(
                'Set TWINFIELD_PURCHASE_TRANSACTION, e.g. INK:202600021, to read a purchase transaction.',
            );
        }

        [$dayBook, $number] = explode(':', $setting) + [1 => ''];

        $transaction = $this->twinfield->purchaseTransactions()->get($dayBook, (int) $number);

        self::assertSame((int) $number, $transaction->number());
        self::assertNotSame('', $transaction->invoiceNumber);
        self::assertNotSame([], $transaction->details);

        // The total line balances with the detail and VAT lines, which are debit for an invoice.
        $lines = array_sum(array_map(static fn ($line): float => (float) $line->signedValue()->value, [
            ...$transaction->details,
            ...$transaction->vat,
        ]));
        self::assertEqualsWithDelta((float) $transaction->value()->value, -$lines, 0.001);

        $input = $transaction->edit();
        self::assertSame($transaction->value()->value, $input->total()->value);
    }

    public function testSearchesOpenInvoicesOfASupplier(): void
    {
        $purchases = array_values(array_filter(
            $this->twinfield->purchaseTransactions()->openInvoices()->limit(100)->get()->items,
            // D: a purchase invoice (or a sales credit note, which supplier() then still finds).
            static fn (OpenInvoice $invoice): bool => $invoice->debitCredit === DebitCredit::Debit,
        ));

        if ($purchases === []) {
            self::markTestSkipped('The company has no open purchase invoices.');
        }

        $supplier = $purchases[0]->relation;
        $ofSupplier = $this->twinfield->purchaseTransactions()->openInvoices()->supplier($supplier)->get();

        self::assertNotSame([], $ofSupplier->items);

        foreach ($ofSupplier->items as $invoice) {
            self::assertSame($supplier, $invoice->relation);
        }
    }
}
