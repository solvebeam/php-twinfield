<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\ReversalTransaction;

use DateTimeImmutable;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\JournalTransaction\JournalTransactionInput;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates a provisional journal transaction in day book MEMO, saves its reversal settings, reads
 * them, undoes them and deletes the transaction again. Never posts and never calls postFinal():
 * a final transaction and its reversed transaction cannot be deleted.
 *
 * Moves 1.00 between the first two profit and loss accounts of the company.
 */
final class ReversalTransactionResourceTest extends WriteTestCase
{
    public function testSetsAndUndoesTheReversalOfAProvisionalTransaction(): void
    {
        $journal = $this->twinfield->journalTransactions();
        $reversals = $this->twinfield->reversalTransactions();
        [$from, $to] = $this->profitAndLossAccounts();

        $created = $journal->create(
            JournalTransactionInput::create('MEMO', new DateTimeImmutable('today'))
                ->debit($to, '1.00', description: 'PHP SDK write test')
                ->credit($from, '1.00', description: 'PHP SDK write test'),
        );

        try {
            $before = $reversals->get('MEMO', $created->number());

            self::assertNull($before->reversed);

            $saved = $reversals->postProvisional('MEMO', $created->number());

            self::assertNull($saved->reversed, 'A provisional transaction has no reversed transaction yet.');

            $read = $reversals->get('MEMO', $created->number());

            self::assertNull($read->reversed);
            self::assertSame($saved->reverseInNextPeriod, $read->reverseInNextPeriod);

            $reversals->undo('MEMO', $created->number());

            self::assertNull($reversals->get('MEMO', $created->number())->reversed);
        } finally {
            $journal->delete('MEMO', $created->number(), 'PHP SDK write test');
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
