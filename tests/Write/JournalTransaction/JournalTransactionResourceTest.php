<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\JournalTransaction;

use DateTimeImmutable;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\JournalTransaction\JournalTransactionInput;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\FailedTransaction;
use SolveBeam\Twinfield\Transaction\SavedTransaction;
use SolveBeam\Twinfield\Transaction\TransactionStatus;

/**
 * Creates provisional journal transactions in day book MEMO, reads, updates and deletes them;
 * always deletes them again. Never posts: a final transaction cannot be deleted.
 *
 * Moves 1.00 between the first two profit and loss accounts of the company.
 */
final class JournalTransactionResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $journal = $this->twinfield->journalTransactions();
        [$from, $to] = $this->profitAndLossAccounts();

        $created = $journal->create(
            JournalTransactionInput::create('MEMO', new DateTimeImmutable('today'))
                ->freeText(1, 'PHP SDK write test')
                ->debit($to, '1.00', description: 'PHP SDK write test')
                ->credit($from, '1.00', description: 'PHP SDK write test'),
        );

        try {
            self::assertSame(TransactionStatus::Provisional, $created->header->status);
            self::assertSame('MEMO', $created->dayBook());
            self::assertSame(Regime::Generic, $created->regime, 'Twinfield books Generic without regime.');
            self::assertCount(2, $created->details);
            self::assertSame(DebitCredit::Debit, $created->details[0]->debitCredit);
            self::assertSame('1.00', $created->debit()->value);

            $read = $journal->get('MEMO', $created->number());

            self::assertSame($created->number(), $read->number());

            $updated = $journal->update(
                $read->edit()->mapLines(
                    static fn ($line, int $index) => $line->description('PHP SDK write test (updated)'),
                ),
            );

            self::assertSame($created->number(), $updated->number());
            self::assertSame('PHP SDK write test (updated)', $updated->details[0]->description);
        } finally {
            $journal->delete('MEMO', $created->number(), 'PHP SDK write test');
        }

        self::assertNull($journal->find('MEMO', $created->number()));
    }

    public function testSaveManySavesTheValidTransactions(): void
    {
        $journal = $this->twinfield->journalTransactions();
        $date = new DateTimeImmutable('today');
        [$from, $to] = $this->profitAndLossAccounts();

        $result = $journal->saveMany(
            JournalTransactionInput::create('MEMO', $date)->debit($to, '2.34')->credit($from, '2.34'),
            JournalTransactionInput::create('MEMO', $date)->debit('NOPE9999', '3.45')->credit($from, '3.45'),
        );

        try {
            self::assertCount(2, $result);
            self::assertInstanceOf(SavedTransaction::class, $result->results[0]);
            self::assertInstanceOf(FailedTransaction::class, $result->results[1]);
            self::assertNotSame([], $result->results[1]->errors);
        } finally {
            foreach ($result->saved() as $saved) {
                $journal->delete($saved->transaction->dayBook(), $saved->transaction->number(), 'PHP SDK write test');
            }
        }
    }

    /**
     * @return array{string, string}
     */
    private function profitAndLossAccounts(): array
    {
        $rows = $this->twinfield->finder(FinderType::Dimensions)->option('dimtype', 'PNL')->limit(2)->get()->rows;

        if (count($rows) < 2) {
            self::markTestSkipped('The company has fewer than two profit and loss accounts.');
        }

        return [$rows[0][0], $rows[1][0]];
    }
}
