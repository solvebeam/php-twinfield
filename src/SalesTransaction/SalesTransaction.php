<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesTransaction;

use DateTimeImmutable;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\DetailLine;
use SolveBeam\Twinfield\Transaction\DetailLineInput;
use SolveBeam\Twinfield\Transaction\DimensionReference;
use SolveBeam\Twinfield\Transaction\InvoiceTotalLine;
use SolveBeam\Twinfield\Transaction\MatchStatus;
use SolveBeam\Twinfield\Transaction\TransactionHeader;
use SolveBeam\Twinfield\Transaction\TransactionLines;
use SolveBeam\Twinfield\Transaction\TransactionXml;
use SolveBeam\Twinfield\Transaction\VatLine;
use SolveBeam\Twinfield\Transaction\VatLineInput;

/**
 * A sales transaction as Twinfield returns it: the booking of one sales invoice or credit note.
 *
 *   $transaction = $twinfield->salesTransactions()->get('VRK', 202600012);
 *
 *   echo $transaction->invoiceNumber, ' ', $transaction->customer()->name, ' ', $transaction->value(), PHP_EOL;
 *
 *   foreach ($transaction->details as $line) {
 *       echo $line->dim1->code, ' ', $line->value, ' ', $line->vatCode, PHP_EOL;
 *   }
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/
 */
final readonly class SalesTransaction
{
    /**
     * @param list<DetailLine> $details
     * @param list<VatLine> $vat
     */
    public function __construct(
        public TransactionHeader $header,
        /** Invoice number, e.g. `2026-0042`; not the transaction number. */
        public string $invoiceNumber,
        public DateTimeImmutable $dueDate,
        /** Payment reference, e.g. a structured reference; null when not set. */
        public ?string $paymentReference,
        /** The line with the customer and the total including VAT. */
        public InvoiceTotalLine $total,
        /** The revenue, without VAT. */
        public array $details,
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
        $header = $transaction->child('header');
        $linesElement = $transaction->child('lines');
        $lines = TransactionLines::fromXml($linesElement);

        return new self(
            header: TransactionHeader::fromXml($transaction, $organisation),
            invoiceNumber: $header->string('invoicenumber'),
            dueDate: TransactionXml::date($header, 'duedate'),
            // Left out when the transaction has none.
            paymentReference: TransactionXml::optionalString($header, 'paymentreference'),
            total: $lines->requireInvoiceTotal($linesElement),
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
     * Shortcut for `$transaction->total->dim2`.
     */
    public function customer(): DimensionReference
    {
        return $this->total->dim2;
    }

    /**
     * A credit note: the total line is credit.
     */
    public function isCreditNote(): bool
    {
        return $this->total->debitCredit === DebitCredit::Credit;
    }

    /**
     * Total including VAT, signed: positive for an invoice, negative for a credit note.
     */
    public function value(): Amount
    {
        $value = $this->total->value;

        return $this->total->debitCredit === DebitCredit::Debit ? $value : $value->negate();
    }

    /**
     * Fully paid: matched with payments.
     */
    public function isPaid(): bool
    {
        return $this->total->matchStatus === MatchStatus::Matched;
    }

    /**
     * Input with all values of this transaction, to change and pass to update().
     */
    public function edit(): SalesTransactionInput
    {
        $header = $this->header;
        $total = $this->total;
        $create = $this->isCreditNote() ? SalesTransactionInput::creditNote(...) : SalesTransactionInput::invoice(...);

        return $create($header->dayBook, $total->dim2->code, $this->invoiceNumber, $header->date)
            ->number($header->number)
            ->currency($header->currency)
            ->period($header->period)
            ->dueDate($this->dueDate)
            ->paymentReference($this->paymentReference)
            ->receivablesAccount($total->dim1->code)
            ->description($total->description)
            ->comment($total->comment)
            ->freeChar($total->freeChar)
            ->freeText(1, $header->freeText1)
            ->freeText(2, $header->freeText2)
            ->freeText(3, $header->freeText3)
            ->withLines(...array_map(static fn (DetailLine $line): DetailLineInput => $line->edit(), $this->details))
            ->withVatLines(...array_map(static fn (VatLine $line): VatLineInput => $line->edit(), $this->vat));
    }
}
