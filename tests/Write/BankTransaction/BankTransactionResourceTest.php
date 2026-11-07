<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\BankTransaction;

use DateTimeImmutable;
use SolveBeam\Twinfield\BankBook\BankBook;
use SolveBeam\Twinfield\BankTransaction\BankTransactionInput;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\FailedTransaction;
use SolveBeam\Twinfield\Transaction\SavedTransaction;
use SolveBeam\Twinfield\Transaction\TransactionStatus;

/**
 * Creates provisional bank transactions, reads, updates and deletes them; always deletes them again.
 * Never posts: a final transaction cannot be deleted.
 *
 * Uses the first bank book with a ledger account and the first profit and loss account of the company.
 */
final class BankTransactionResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $bank = $this->twinfield->bankTransactions();
        $bankBook = $this->bankBook();
        $account = $this->profitAndLossAccount();

        $created = $bank->create(
            BankTransactionInput::forBankBook($bankBook, new DateTimeImmutable())
                ->statement(1, startValue: '0.00')
                ->description('PHP SDK write test')
                ->pay($account, '1.23', description: 'PHP SDK write test'),
        );

        try {
            self::assertSame(TransactionStatus::Provisional, $created->header->status);
            self::assertSame($bankBook->code, $created->dayBook());
            self::assertSame('-1.23', $created->balance()->value);
            self::assertSame(DebitCredit::Credit, $created->total->debitCredit);
            self::assertSame('-1.23', $created->closeValue->value);
            self::assertCount(1, $created->details);

            $read = $bank->get($created->dayBook(), $created->number());

            self::assertSame($created->number(), $read->number());
            self::assertSame('1.23', $read->details[0]->amounts->value->value);

            $updated = $bank->update($read->edit()->description('PHP SDK write test (updated)'));

            self::assertSame($created->number(), $updated->number());
            self::assertSame('PHP SDK write test (updated)', $updated->total->description);
        } finally {
            $bank->delete($created->dayBook(), $created->number(), 'PHP SDK write test');
        }

        self::assertNull($bank->find($created->dayBook(), $created->number()));
    }

    public function testSaveManySavesTheValidTransactions(): void
    {
        $bank = $this->twinfield->bankTransactions();
        $base = BankTransactionInput::forBankBook($this->bankBook(), new DateTimeImmutable());

        $result = $bank->saveMany(
            $base->receive($this->profitAndLossAccount(), '2.34', description: 'PHP SDK write test'),
            $base->receive('NOPE9999', '3.45', description: 'PHP SDK write test (invalid)'),
        );

        try {
            self::assertCount(2, $result);
            self::assertInstanceOf(SavedTransaction::class, $result->results[0]);
            self::assertInstanceOf(FailedTransaction::class, $result->results[1]);
            self::assertNotSame([], $result->results[1]->errors);
        } finally {
            foreach ($result->saved() as $saved) {
                $bank->delete($saved->transaction->dayBook(), $saved->transaction->number(), 'PHP SDK write test');
            }
        }
    }

    private function bankBook(): BankBook
    {
        foreach ($this->twinfield->bankBooks()->search()->all() as $summary) {
            $bankBook = $this->twinfield->bankBooks()->get($summary->code);

            if ($bankBook->generalLedgerAccount !== null) {
                return $bankBook;
            }
        }

        self::markTestSkipped('The company has no bank book with a ledger account.');
    }

    private function profitAndLossAccount(): string
    {
        $row = $this->twinfield->finder(FinderType::Dimensions)->option('dimtype', 'PNL')->limit(1)->get()->rows[0] ?? null;

        if ($row === null) {
            self::markTestSkipped('The company has no profit and loss accounts.');
        }

        return $row[0];
    }
}
