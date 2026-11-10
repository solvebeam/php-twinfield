<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\PayAndCollect;

use DateTimeImmutable;
use SolveBeam\Twinfield\PayAndCollect\PayAndCollectException;
use SolveBeam\Twinfield\PayAndCollect\PayRun;
use SolveBeam\Twinfield\PayAndCollect\PayRunInput;
use SolveBeam\Twinfield\PayAndCollect\PayRunModification;
use SolveBeam\Twinfield\PayAndCollect\PayRunOverview;
use SolveBeam\Twinfield\PayAndCollect\PayRunPhase;
use SolveBeam\Twinfield\PayAndCollect\PayRunType;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;
use SolveBeam\Twinfield\Transaction\OpenInvoice;

/**
 * Creates a payment run (pay type SEPANLCT) for one open purchase invoice of the company, reads it,
 * changes the amount, authorises it and deletes it again with finish(). Never posts and never
 * creates a file: those can't be undone.
 *
 * Run with recording to replace the fictitious fixtures:
 *
 *   TWINFIELD_RECORD=1 composer test:write -- --company=1002_3 --filter=PayAndCollect
 */
final class PayAndCollectResourceTest extends WriteTestCase
{
    public function testCreatesModifiesAuthorisesAndDeletesARun(): void
    {
        $payAndCollect = $this->twinfield->payAndCollect();
        $invoice = $this->openPurchaseInvoice();
        $today = new DateTimeImmutable('today');

        $created = $payAndCollect->create(
            PayRunInput::create(
                'SEPANLCT',
                $today->modify('+1 day'),
                FinancialPeriod::of((int) $today->format('Y'), (int) $today->format('n')),
            )
                ->dimensions($invoice->relation, $invoice->relation)
                ->invoiceNumber($invoice->invoiceNumber),
        );
        $run = $created->run;

        try {
            self::assertSame('SEPANLCT', $run->payType);
            self::assertSame(PayRunType::Payment, $run->runType);
            self::assertSame(PayRunPhase::Select, $run->phase);
            self::assertCount(1, $run->summaries, 'One summary for the one invoice.');

            $summary = $run->summaries[0];
            $detail = $summary->details[0];

            self::assertSame($invoice->relation, $summary->relation);
            self::assertSame($invoice->invoiceNumber, $detail->invoiceNumber);

            $listed = array_values(array_filter(
                $payAndCollect->list(),
                static fn (PayRunOverview $overview): bool => $overview->id === $run->id,
            ));

            self::assertCount(1, $listed, 'The new run is in list().');

            $read = $payAndCollect->get($run->payType, $run->id);

            self::assertSame($run->version->touched, $read->version->touched);
            self::assertSame($detail->value->value, $read->summaries[0]->details[0]->value->value);

            $lower = $detail->originalValue->isPositive()
                ? $detail->originalValue->minus('0.01')
                : $detail->originalValue->plus('0.01');
            $version = $payAndCollect->modify(PayRunModification::of($read)->value($summary->id, $detail->id, $lower));

            self::assertGreaterThan($read->version->touched, $version->touched);
            self::assertSame(
                $lower->value,
                $payAndCollect->get($run->payType, $run->id)->summaries[0]->details[0]->value->value,
            );

            try {
                $payAndCollect->authorise($run, $read->version);
                self::fail('Expected a PayAndCollectException for an outdated version.');
            } catch (PayAndCollectException $exception) {
                self::assertSame('Authorize', $exception->operation);
            }

            $payAndCollect->authorise($run, $version);

            self::assertSame(PayRunPhase::Authorise, $payAndCollect->get($run->payType, $run->id)->phase);
        } finally {
            $this->finish($run);
        }

        $remaining = array_filter(
            $payAndCollect->list(),
            static fn (PayRunOverview $overview): bool => $overview->id === $run->id,
        );

        self::assertSame([], $remaining, 'finish() deleted the run.');
    }

    /**
     * Deletes the run with its latest version.
     */
    private function finish(PayRun $run): void
    {
        $payAndCollect = $this->twinfield->payAndCollect();

        $payAndCollect->finish($run, $payAndCollect->get($run->payType, $run->id)->version);
    }

    private function openPurchaseInvoice(): OpenInvoice
    {
        $purchases = array_values(array_filter(
            $this->twinfield->purchaseTransactions()->openInvoices()->limit(100)->get()->items,
            // D: a purchase invoice (or a sales credit note).
            static fn (OpenInvoice $invoice): bool => $invoice->debitCredit === DebitCredit::Debit,
        ));

        return $purchases[0] ?? self::markTestSkipped('The company has no open purchase invoices.');
    }
}
