<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\SalesTransaction;

use DateTimeImmutable;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\SalesTransaction\SalesTransactionInput;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\FailedTransaction;
use SolveBeam\Twinfield\Transaction\SavedTransaction;
use SolveBeam\Twinfield\Transaction\TransactionStatus;
use SolveBeam\Twinfield\VatCode\VatCode;
use SolveBeam\Twinfield\VatCode\VatCodeType;

/**
 * Creates provisional sales transactions in day book VRK, reads, updates, changes the pay status
 * and deletes them; always deletes them again. Never posts: a final transaction cannot be deleted.
 *
 * Uses the first customer, the first profit and loss account and VAT code VH (or the first
 * sales VAT code) of the company.
 */
final class SalesTransactionResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $sales = $this->twinfield->salesTransactions();
        $date = new DateTimeImmutable('today');
        $invoiceNumber = 'SDK-' . date('YmdHis');

        $created = $sales->create(
            SalesTransactionInput::invoice('VRK', $this->customer(), $invoiceNumber, $date)
                ->dueDate($date->modify('+30 days'))
                ->description('PHP SDK write test')
                ->revenue($this->profitAndLossAccount(), '1.00', vatCode: $this->vatCode(), description: 'PHP SDK write test'),
        );

        try {
            self::assertSame(TransactionStatus::Provisional, $created->header->status);
            self::assertSame('VRK', $created->dayBook());
            self::assertSame($invoiceNumber, $created->invoiceNumber);
            self::assertSame($this->customer(), $created->customer()->code);
            self::assertSame(DebitCredit::Debit, $created->total->debitCredit);
            self::assertEquals($date->modify('+30 days'), $created->dueDate);
            self::assertCount(1, $created->details);

            $read = $sales->get('VRK', $created->number());

            self::assertSame($created->number(), $read->number());
            self::assertSame($created->value()->value, $read->value()->value);

            $updated = $sales->update($read->edit()->paymentReference($invoiceNumber)->description('PHP SDK write test (updated)'));

            self::assertSame($created->number(), $updated->number());
            self::assertSame($invoiceNumber, $updated->paymentReference);
            self::assertSame('PHP SDK write test (updated)', $updated->total->description);

            $sales->excludeFromCollection('VRK', $created->number());
            $sales->includeInCollection('VRK', $created->number());
        } finally {
            $sales->delete('VRK', $created->number(), 'PHP SDK write test');
        }

        self::assertNull($sales->find('VRK', $created->number()));
    }

    public function testCreatesACreditNote(): void
    {
        $sales = $this->twinfield->salesTransactions();

        $created = $sales->create(
            SalesTransactionInput::creditNote('VRK', $this->customer(), 'SDK-C-' . date('YmdHis'), new DateTimeImmutable('today'))
                ->revenue($this->profitAndLossAccount(), '1.00', vatCode: $this->vatCode()),
        );

        try {
            self::assertTrue($created->isCreditNote());
            self::assertSame(DebitCredit::Credit, $created->total->debitCredit);
            self::assertSame(DebitCredit::Debit, $created->details[0]->debitCredit);
        } finally {
            $sales->delete('VRK', $created->number(), 'PHP SDK write test');
        }
    }

    public function testSaveManySavesTheValidTransactions(): void
    {
        $sales = $this->twinfield->salesTransactions();
        $date = new DateTimeImmutable('today');
        $account = $this->profitAndLossAccount();

        $result = $sales->saveMany(
            SalesTransactionInput::invoice('VRK', $this->customer(), 'SDK-M1-' . date('YmdHis'), $date)
                ->revenue($account, '2.34', vatCode: $this->vatCode()),
            SalesTransactionInput::invoice('VRK', 'NOPE9999', 'SDK-M2-' . date('YmdHis'), $date)
                ->revenue($account, '3.45', vatCode: $this->vatCode()),
        );

        try {
            self::assertCount(2, $result);
            self::assertInstanceOf(SavedTransaction::class, $result->results[0]);
            self::assertInstanceOf(FailedTransaction::class, $result->results[1]);
            self::assertNotSame([], $result->results[1]->errors);
        } finally {
            foreach ($result->saved() as $saved) {
                $sales->delete($saved->transaction->dayBook(), $saved->transaction->number(), 'PHP SDK write test');
            }
        }
    }

    private function customer(): string
    {
        return $this->twinfield->customers()->search()->limit(1)->get()->items[0]->code
            ?? self::markTestSkipped('The company has no customers.');
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
            $this->twinfield->vatCodes()->search()->vatType(VatCodeType::Sales)->get()->items,
        );

        if ($codes === []) {
            self::markTestSkipped('The company has no sales VAT code.');
        }

        return $this->twinfield->vatCodes()->get(in_array('VH', $codes, true) ? 'VH' : $codes[0]);
    }
}
