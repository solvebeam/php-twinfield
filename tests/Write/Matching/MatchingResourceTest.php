<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\Matching;

use DateTimeImmutable;
use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\JournalTransaction\JournalTransactionInput;
use SolveBeam\Twinfield\Matching\MatchInput;
use SolveBeam\Twinfield\Matching\MatchNotFoundException;
use SolveBeam\Twinfield\SalesTransaction\SalesTransactionInput;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\VatCode\VatCode;
use SolveBeam\Twinfield\VatCode\VatCodeType;

/**
 * Creates a provisional sales invoice in day book VRK and a provisional journal transaction in
 * MEMO that pays it, matches the two lines, finds the match number in browse data (100), undoes
 * the match and deletes both transactions again. Never posts: a final transaction cannot be deleted.
 *
 * Uses the first customer, the first profit and loss account and VAT code VH (or the first sales
 * VAT code) of the company.
 */
final class MatchingResourceTest extends WriteTestCase
{
    public function testMatchesAndUndoes(): void
    {
        $sales = $this->twinfield->salesTransactions();
        $journal = $this->twinfield->journalTransactions();
        $matching = $this->twinfield->matching();
        $date = new DateTimeImmutable('today');
        $customer = $this->customer();
        $account = $this->profitAndLossAccount();
        $invoiceNumber = 'SDK-' . date('YmdHis');

        $invoice = $sales->create(
            SalesTransactionInput::invoice('VRK', $customer, $invoiceNumber, $date)
                ->revenue($account, '1.00', vatCode: $this->vatCode(), description: 'PHP SDK write test'),
        );

        try {
            $payment = $journal->create(
                JournalTransactionInput::create('MEMO', $date)
                    ->credit(
                        $invoice->total->dim1->code,
                        $invoice->value(),
                        customer: $customer,
                        invoice: $invoiceNumber,
                        description: 'PHP SDK write test',
                    )
                    ->debit($account, $invoice->value(), description: 'PHP SDK write test'),
            );

            try {
                $paymentLine = $payment->details[0];

                self::assertSame($customer, $paymentLine->dim2?->code);

                $matching->match(
                    MatchInput::customers($date)
                        ->line('VRK', $invoice->number(), $invoice->total->id)
                        ->line('MEMO', $payment->number(), $paymentLine->id),
                );

                $matchNumber = $this->matchNumber($customer, $invoice->number());

                $matching->undo(DimensionTypeCode::AccountsReceivable, $customer, $matchNumber);

                try {
                    $matching->undo(DimensionTypeCode::AccountsReceivable, $customer, $matchNumber);
                    self::fail('Expected MatchNotFoundException after undoing the match.');
                } catch (MatchNotFoundException $exception) {
                    self::assertNotSame('', $exception->messages[0]->text);
                }
            } finally {
                $journal->delete('MEMO', $payment->number(), 'PHP SDK write test');
            }
        } finally {
            $sales->delete('VRK', $invoice->number(), 'PHP SDK write test');
        }
    }

    private function matchNumber(string $customer, int $invoice): int
    {
        $result = $this->twinfield->browse(BrowseCode::CustomerTransactions)
            ->columns('fin.trs.line.dim2', 'fin.trs.head.code', 'fin.trs.head.number', 'fin.trs.line.matchnumber')
            ->between('fin.trs.head.yearperiod', '2000/00', '2099/55')
            ->between('fin.trs.line.dim2', $customer, $customer)
            ->equal('fin.trs.head.code', 'VRK')
            ->between('fin.trs.head.number', (string) $invoice, (string) $invoice)
            ->get();

        self::assertCount(1, $result, 'The invoice line in browse data.');

        foreach ($result as $row) {
            $matchNumber = $row->nullableString('fin.trs.line.matchnumber');

            self::assertNotNull($matchNumber, 'The matched invoice has a match number.');
            self::assertMatchesRegularExpression('~^\d+$~', $matchNumber);

            return (int) $matchNumber;
        }

        self::fail('No browse row.');
    }

    private function customer(): string
    {
        return $this->twinfield->customers()->search()->limit(1)->get()->items[0]->code
            ?? self::markTestSkipped('The company has no customers.');
    }

    private function profitAndLossAccount(): string
    {
        $rows = $this->twinfield->finder(FinderType::Dimensions)->option('dimtype', 'PNL')->limit(1)->get()->rows;
        $row = $rows[0] ?? null;

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
