<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\JournalTransaction;

use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;

/**
 * Live journal transaction reads (read-only) against TWINFIELD_COMPANY.
 *
 * Set TWINFIELD_JOURNAL_TRANSACTION to an existing journal transaction, e.g. `MEMO:202600003`,
 * to also read one: there is no search that gives transaction numbers.
 */
final class JournalTransactionResourceTest extends IntegrationTestCase
{
    public function testThrowsNotFoundForAnUnknownNumber(): void
    {
        $this->expectException(TransactionNotFoundException::class);

        $this->twinfield->journalTransactions()->get('MEMO', 209999999);
    }

    public function testReadsAJournalTransaction(): void
    {
        $setting = $this->environment->config->get('TWINFIELD_JOURNAL_TRANSACTION');

        if ($setting === null) {
            self::markTestSkipped(
                'Set TWINFIELD_JOURNAL_TRANSACTION, e.g. MEMO:202600003, to read a journal transaction.',
            );
        }

        [$dayBook, $number] = explode(':', $setting) + [1 => ''];

        try {
            $transaction = $this->twinfield->journalTransactions()->get($dayBook, (int) $number);
        } catch (TransactionNotFoundException $exception) {
            self::assertTrue($exception->deleted, $exception->getMessage());
            self::markTestSkipped(sprintf('Journal transaction %s was deleted.', $setting));
        }

        self::assertSame((int) $number, $transaction->number());
        self::assertGreaterThanOrEqual(2, count($transaction->details) + count($transaction->vat));
        self::assertSame($transaction->debit()->value, $transaction->credit()->value, 'The lines balance.');

        $input = $transaction->edit();
        self::assertTrue($input->balance()->isZero());
        $input->toXml($transaction->header->company->code);
    }
}
