<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\SpreadTransaction;

use DateTimeImmutable;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\PurchaseTransaction\PurchaseTransactionInput;
use SolveBeam\Twinfield\SpreadTransaction\SpreadLine;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates a provisional purchase transaction in day book INK, spreads it over two periods, reads
 * the spread, undoes it, spreads it again by lines, undoes that and deletes the transaction.
 * Never posts and never calls postFinal() or postFinalByLines(): a final transaction and its
 * spread transactions cannot be deleted.
 *
 * Uses the first supplier, the first profit and loss account and the first balance sheet account
 * (as intermediate account) of the company. Prints what Twinfield answers, to record the
 * behaviour the documentation leaves open.
 */
final class SpreadTransactionResourceTest extends WriteTestCase
{
    public function testSpreadsAndUndoesAProvisionalTransaction(): void
    {
        $purchases = $this->twinfield->purchaseTransactions();
        $spreads = $this->twinfield->spreadTransactions();
        $date = new DateTimeImmutable('today');

        $created = $purchases->create(
            PurchaseTransactionInput::invoice('INK', $this->supplier(), 'SDK-S-' . date('YmdHis'), $date)
                ->description('PHP SDK write test')
                ->expense($this->account('PNL'), '2.00', description: 'PHP SDK write test'),
        );

        $start = $created->header->period;
        $end = $start->number < 12 ? Period::of($start->year, $start->number + 1) : Period::of($start->year + 1, 1);
        $intermediateAccount = $this->account('BAS');

        try {
            $before = $spreads->get('INK', $created->number());

            self::assertFalse($before->isSpread());
            fwrite(STDERR, sprintf("Settings before the spread: %s\n", $before->settings === null ? 'none' : 'set'));

            $saved = $spreads->postProvisional('INK', $created->number(), $intermediateAccount, $start, $end);

            self::assertNotNull($saved->settings);
            self::assertSame($intermediateAccount, $saved->settings->intermediateAccount);
            self::assertSame((string) $start, (string) $saved->settings->startPeriod);
            self::assertSame((string) $end, (string) $saved->settings->endPeriod);
            fwrite(STDERR, sprintf(
                "Spread transactions after postProvisional(): %d\n",
                count($saved->spreadTransactions),
            ));

            $read = $spreads->get('INK', $created->number());

            self::assertEquals($saved->settings, $read->settings);
            self::assertEquals($saved->spreadTransactions, $read->spreadTransactions);

            $spreads->undo('INK', $created->number());

            self::assertFalse($spreads->get('INK', $created->number())->isSpread());

            $byLines = $spreads->postProvisionalByLines(
                'INK',
                $created->number(),
                $intermediateAccount,
                new SpreadLine($created->details[0]->id, $start, $end),
            );

            self::assertNotNull($byLines->settings);
            self::assertTrue($byLines->settings->isByLines());
            self::assertSame($created->details[0]->id, $byLines->settings->lines[0]->lineId);

            // All periods equal to the period of the transaction: Twinfield should undo the spread.
            $removed = $spreads->postProvisionalByLines(
                'INK',
                $created->number(),
                $intermediateAccount,
                new SpreadLine($created->details[0]->id, $start, $start),
            );

            fwrite(STDERR, sprintf(
                "After a spread by lines in the period of the transaction: %d spread transactions, settings %s\n",
                count($removed->spreadTransactions),
                $removed->settings === null ? 'none' : 'set',
            ));
        } finally {
            try {
                $spreads->undo('INK', $created->number());
            } catch (ProcessXmlException) {
                // Nothing to undo: the spread is already removed.
            } finally {
                $purchases->delete('INK', $created->number(), 'PHP SDK write test');
            }
        }
    }

    private function supplier(): string
    {
        return $this->twinfield->suppliers()->search()->limit(1)->get()->items[0]->code
            ?? self::markTestSkipped('The company has no suppliers.');
    }

    private function account(string $dimensionType): string
    {
        $row = $this->twinfield->finder(FinderType::Dimensions)
            ->option('dimtype', $dimensionType)
            ->limit(1)
            ->get()
            ->rows[0] ?? null;

        if ($row === null) {
            self::markTestSkipped(sprintf('The company has no %s accounts.', $dimensionType));
        }

        return $row[0];
    }
}
