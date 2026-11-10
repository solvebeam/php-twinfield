<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\JournalTransaction;

use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\DetailLine;
use SolveBeam\Twinfield\Transaction\DetailLineInput;
use SolveBeam\Twinfield\Transaction\TransactionHeader;
use SolveBeam\Twinfield\Transaction\TransactionLines;
use SolveBeam\Twinfield\Transaction\VatLine;
use SolveBeam\Twinfield\Transaction\VatLineInput;

/**
 * A journal transaction as Twinfield returns it: a memorial booking without total line.
 *
 *   $transaction = $twinfield->journalTransactions()->get('MEMO', 202600003);
 *
 *   foreach ($transaction->details as $line) {
 *       echo $line->account->code, ' ', $line->debitCredit->value, ' ', $line->value, PHP_EOL;
 *   }
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/journal-transactions/
 */
final readonly class JournalTransaction
{
    /**
     * @param list<DetailLine> $details
     * @param list<VatLine> $vat
     */
    public function __construct(
        public TransactionHeader $header,
        /** Regime the transaction is booked in; `Generic` is visible in all regimes. */
        public Regime $regime,
        public array $details,
        /** The VAT of detail lines with a VAT code, booked by Twinfield on the VAT accounts. */
        public array $vat,
    ) {
    }

    /**
     * From a `<transaction>` element of a read or save response.
     *
     * @param Organisation $organisation Organisation of the access token: Twinfield leaves it out
     *                                   of the response.
     */
    public static function fromXml(XmlElement $transaction, Organisation $organisation): self
    {
        $linesElement = $transaction->child('lines');
        $lines = TransactionLines::fromXml($linesElement);
        $lines->requireNoTotal($linesElement);

        return new self(
            header: TransactionHeader::fromXml($transaction, $organisation),
            // Not in the documented response, but in every live response.
            regime: $transaction->child('header')->enum('regime', Regime::class),
            details: $lines->details,
            vat: $lines->vat,
        );
    }

    /**
     * Shortcut for `$transaction->header->number`.
     */
    public function number(): int
    {
        return $this->header->number;
    }

    /**
     * Shortcut for `$transaction->header->dayBook`.
     */
    public function dayBook(): string
    {
        return $this->header->dayBook;
    }

    /**
     * Sum of the debit lines (detail and VAT); equal to credit() because the transaction balances.
     */
    public function debit(): Amount
    {
        return $this->sum(DebitCredit::Debit);
    }

    /**
     * Sum of the credit lines (detail and VAT).
     */
    public function credit(): Amount
    {
        return $this->sum(DebitCredit::Credit);
    }

    /**
     * Input with all values of this transaction, to change and pass to update().
     */
    public function edit(): JournalTransactionInput
    {
        $header = $this->header;

        return JournalTransactionInput::create($header->dayBook, $header->date)
            ->number($header->number)
            ->currency($header->currency)
            ->period($header->period)
            ->regime($this->regime)
            ->freeText(1, $header->freeText1)
            ->freeText(2, $header->freeText2)
            ->freeText(3, $header->freeText3)
            ->withLines(...array_map(static fn (DetailLine $line): DetailLineInput => $line->edit(), $this->details))
            ->withVatLines(...array_map(static fn (VatLine $line): VatLineInput => $line->edit(), $this->vat));
    }

    private function sum(DebitCredit $side): Amount
    {
        $amounts = [];

        foreach ([...$this->details, ...$this->vat] as $line) {
            if ($line->debitCredit === $side) {
                $amounts[] = $line->value;
            }
        }

        return Amount::sum($amounts);
    }
}
