<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\PurchaseTransaction;

use DateTimeImmutable;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\PurchaseTransaction\PurchaseTransactionInput;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\FailedTransaction;
use SolveBeam\Twinfield\Transaction\SavedTransaction;
use SolveBeam\Twinfield\Transaction\TransactionStatus;
use SolveBeam\Twinfield\VatCode\VatCode;
use SolveBeam\Twinfield\VatCode\VatCodeType;

/**
 * Creates provisional purchase transactions in day book INK, reads, updates, changes the pay
 * status and deletes them; always deletes them again. Never posts: a final transaction cannot be
 * deleted.
 *
 * Uses the first supplier, the first profit and loss account and VAT code IH (or the first
 * purchase VAT code) of the company.
 */
final class PurchaseTransactionResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $purchases = $this->twinfield->purchaseTransactions();
        $date = new DateTimeImmutable('today');
        $invoiceNumber = 'SDK-' . date('YmdHis');

        $created = $purchases->create(
            PurchaseTransactionInput::invoice('INK', $this->supplier(), $invoiceNumber, $date)
                ->dueDate($date->modify('+30 days'))
                ->description('PHP SDK write test')
                ->expense($this->profitAndLossAccount(), '1.00', vatCode: $this->vatCode(), description: 'PHP SDK write test'),
        );

        try {
            self::assertSame(TransactionStatus::Provisional, $created->header->status);
            self::assertSame('INK', $created->dayBook());
            self::assertSame($invoiceNumber, $created->invoiceNumber);
            self::assertSame($this->supplier(), $created->supplier()->code);
            self::assertSame(DebitCredit::Credit, $created->total->debitCredit);
            self::assertEquals($date->modify('+30 days'), $created->dueDate);
            self::assertCount(1, $created->details);

            $read = $purchases->get('INK', $created->number());

            self::assertSame($created->number(), $read->number());
            self::assertSame($created->value()->value, $read->value()->value);

            $updated = $purchases->update($read->edit()->paymentReference($invoiceNumber)->description('PHP SDK write test (updated)'));

            self::assertSame($created->number(), $updated->number());
            self::assertSame($invoiceNumber, $updated->paymentReference);
            self::assertSame('PHP SDK write test (updated)', $updated->total->description);

            $purchases->excludeFromPaymentRuns('INK', $created->number());
            $purchases->includeInPaymentRuns('INK', $created->number());
        } finally {
            $purchases->delete('INK', $created->number(), 'PHP SDK write test');
        }

        self::assertNull($purchases->find('INK', $created->number()));
    }

    public function testCreatesACreditNote(): void
    {
        $purchases = $this->twinfield->purchaseTransactions();

        $created = $purchases->create(
            PurchaseTransactionInput::creditNote('INK', $this->supplier(), 'SDK-C-' . date('YmdHis'), new DateTimeImmutable('today'))
                ->expense($this->profitAndLossAccount(), '1.00', vatCode: $this->vatCode()),
        );

        try {
            self::assertTrue($created->isCreditNote());
            self::assertSame(DebitCredit::Debit, $created->total->debitCredit);
            self::assertSame(DebitCredit::Credit, $created->details[0]->debitCredit);
        } finally {
            $purchases->delete('INK', $created->number(), 'PHP SDK write test');
        }
    }

    public function testSaveManySavesTheValidTransactions(): void
    {
        $purchases = $this->twinfield->purchaseTransactions();
        $date = new DateTimeImmutable('today');
        $account = $this->profitAndLossAccount();

        $result = $purchases->saveMany(
            PurchaseTransactionInput::invoice('INK', $this->supplier(), 'SDK-M1-' . date('YmdHis'), $date)
                ->expense($account, '2.34', vatCode: $this->vatCode()),
            PurchaseTransactionInput::invoice('INK', 'NOPE9999', 'SDK-M2-' . date('YmdHis'), $date)
                ->expense($account, '3.45', vatCode: $this->vatCode()),
        );

        try {
            self::assertCount(2, $result);
            self::assertInstanceOf(SavedTransaction::class, $result->results[0]);
            self::assertInstanceOf(FailedTransaction::class, $result->results[1]);
            self::assertNotSame([], $result->results[1]->errors);
        } finally {
            foreach ($result->saved() as $saved) {
                $purchases->delete($saved->transaction->dayBook(), $saved->transaction->number(), 'PHP SDK write test');
            }
        }
    }

    private function supplier(): string
    {
        return $this->twinfield->suppliers()->search()->limit(1)->get()->items[0]->code
            ?? self::markTestSkipped('The company has no suppliers.');
    }

    private function profitAndLossAccount(): string
    {
        $row = $this->twinfield->finder(FinderType::Dimensions)->option('dimtype', 'PNL')->limit(1)->get()->rows[0] ?? null;

        if ($row === null) {
            self::markTestSkipped('The company has no profit and loss accounts.');
        }

        return $row[0];
    }

    private function vatCode(): VatCode
    {
        $codes = array_map(
            static fn ($vatCode): string => $vatCode->code,
            $this->twinfield->vatCodes()->search()->vatType(VatCodeType::Purchase)->get()->items,
        );

        if ($codes === []) {
            self::markTestSkipped('The company has no purchase VAT code.');
        }

        return $this->twinfield->vatCodes()->get(in_array('IH', $codes, true) ? 'IH' : $codes[0]);
    }
}
