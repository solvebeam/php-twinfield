<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesTransaction;

use DateTimeImmutable;
use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\AbstractTransactionInput;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\DetailLineInput;
use SolveBeam\Twinfield\Transaction\LineType;
use SolveBeam\Twinfield\Transaction\TransactionXmlWriter;
use SolveBeam\Twinfield\VatCode\VatCode;

/**
 * A sales transaction to create or update: the booking of an invoice you made in your own
 * software. Immutable: every method returns a new input.
 *
 *   $input = SalesTransactionInput::invoice('VRK', customer: '1000', invoiceNumber: '2026-0042', date: $date)
 *       ->dueDate($date->modify('+30 days'))
 *       ->revenue('8000', '100.00', vatCode: 'VH', vatValue: '21.00', description: 'Consultancy')
 *       ->revenue('8010', '50.00', vatCode: $twinfield->vatCodes()->get('VL'));
 *
 *   $transaction = $twinfield->salesTransactions()->create($input);
 *
 * revenue() books revenue without VAT: a credit line for an invoice, a debit line for a credit
 * note. The total line with the customer and the amount including VAT is calculated from the
 * lines, so the transaction always balances. New transactions are provisional unless you call
 * final().
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/
 */
final class SalesTransactionInput extends AbstractTransactionInput
{
    private ?string $account = null;

    private ?DateTimeImmutable $dueDate = null;

    private ?string $paymentReference = null;

    private ?bool $duplicateInvoiceWarning = null;

    private ?string $description = null;

    private ?string $comment = null;

    private ?string $freeChar = null;

    private function __construct(
        string $dayBook,
        private readonly string $customer,
        private readonly string $invoiceNumber,
        DateTimeImmutable $date,
        private readonly bool $creditNote,
    ) {
        parent::__construct($dayBook, $date);

        TransactionXmlWriter::dimension('customer (dim2 of the total line)', $customer);

        if ($invoiceNumber === '') {
            throw new InvalidArgumentException('A sales transaction needs an invoice number.');
        }

        XmlValue::maxLength('invoice number', $invoiceNumber, 40);
    }

    /**
     * A sales invoice: revenue() adds credit lines, the total line is debit.
     *
     * @param string $dayBook Sales day book (transaction type) code, e.g. `VRK`.
     * @param string $customer Customer code (`dim2` of the total line), e.g. `1000`.
     * @param string $invoiceNumber Invoice number of your own software, at most 40 characters.
     * @param DateTimeInterface $date Invoice date; Twinfield derives the period from it.
     */
    public static function invoice(
        string $dayBook,
        string $customer,
        string $invoiceNumber,
        DateTimeInterface $date,
    ): self {
        return new self($dayBook, $customer, $invoiceNumber, DateTimeImmutable::createFromInterface($date), false);
    }

    /**
     * A credit note: revenue() adds debit lines, the total line is credit. Amounts stay positive.
     *
     * @param string $dayBook Sales day book (transaction type) code, e.g. `VRK`.
     * @param string $customer Customer code (`dim2` of the total line), e.g. `1000`.
     * @param string $invoiceNumber Number of the credit note, at most 40 characters.
     * @param DateTimeInterface $date Date of the credit note.
     */
    public static function creditNote(
        string $dayBook,
        string $customer,
        string $invoiceNumber,
        DateTimeInterface $date,
    ): self {
        return new self($dayBook, $customer, $invoiceNumber, DateTimeImmutable::createFromInterface($date), true);
    }

    /**
     * Revenue without VAT: a credit line for an invoice, a debit line for a credit note.
     *
     *   ->revenue('8000', '100.00', vatCode: 'VH', vatValue: '21.00')
     *   ->revenue('8000', '100.00', vatCode: $twinfield->vatCodes()->get('VH'))
     *
     * With a VatCode and no VAT amount, the VAT is calculated with the percentage of the VAT
     * code on the transaction date. With a VAT code as string, give the VAT amount too.
     *
     * @param string $account `dim1`: the profit and loss account, e.g. `8000`.
     * @param Amount|numeric-string $amount Amount without VAT.
     * @param Amount|numeric-string|null $vatValue VAT amount; Twinfield refuses the transaction when it differs.
     * @param ?string $costCenter `dim2`: cost center.
     * @param ?string $project `dim3`: project or asset.
     */
    public function revenue(
        string $account,
        Amount|string $amount,
        string|VatCode|null $vatCode = null,
        Amount|string|null $vatValue = null,
        ?string $costCenter = null,
        ?string $project = null,
        ?string $description = null,
    ): self {
        $amount = Amount::of($amount);

        if ($vatCode instanceof VatCode) {
            $vatValue ??= $vatCode->vatOn($amount, $this->date());
            $vatCode = $vatCode->code;
        }

        $line = DetailLineInput::create($this->creditNote ? DebitCredit::Debit : DebitCredit::Credit, $account, $amount)
            ->costCenter($costCenter)
            ->project($project)
            ->description($description)
            ->vat($vatCode, $vatValue);

        return $this->line($line);
    }

    /**
     * Due date of the invoice, e.g. the invoice date plus the payment term.
     */
    public function dueDate(?DateTimeInterface $date): self
    {
        $input = clone $this;
        $input->dueDate = $date === null ? null : DateTimeImmutable::createFromInterface($date);

        return $input;
    }

    /**
     * Payment reference, at most 50 characters, e.g. a structured reference `+++100/0160/01495+++`.
     */
    public function paymentReference(?string $reference): self
    {
        $input = clone $this;
        $input->paymentReference = TransactionXmlWriter::optionalText('payment reference', $reference, 50);

        return $input;
    }

    /**
     * No warning when the invoice number is already used (`invoicenumber/@raisewarning`).
     */
    public function ignoreDuplicateInvoiceWarning(): self
    {
        $input = clone $this;
        $input->duplicateInvoiceWarning = false;

        return $input;
    }

    /**
     * `dim1` of the total line: the accounts receivable account, e.g. `1300`. Without it,
     * Twinfield takes the account of the customer.
     */
    public function receivablesAccount(?string $account): self
    {
        if ($account !== null) {
            TransactionXmlWriter::dimension('receivables account (dim1 of the total line)', $account);
        }

        $input = clone $this;
        $input->account = $account;

        return $input;
    }

    /**
     * Description of the total line, at most 40 characters.
     */
    public function description(?string $description): self
    {
        $input = clone $this;
        $input->description = TransactionXmlWriter::optionalText('total line description', $description, 40);

        return $input;
    }

    /**
     * Comment on the total line, at most 255 characters.
     */
    public function comment(?string $comment): self
    {
        $input = clone $this;
        $input->comment = TransactionXmlWriter::optionalText('total line comment', $comment, 255);

        return $input;
    }

    /**
     * Leave the invoice out of the direct debit runs of Twinfield (`freechar` `N` on the total line).
     */
    public function excludeFromDirectDebit(bool $exclude = true): self
    {
        return $this->freeChar($exclude ? 'N' : null);
    }

    /**
     * Free character field of the total line: one character. `N` leaves the invoice out of the
     * direct debit runs; see excludeFromDirectDebit().
     */
    public function freeChar(?string $freeChar): self
    {
        $freeChar = $freeChar === '' ? null : $freeChar;

        if ($freeChar !== null && mb_strlen($freeChar) !== 1) {
            throw new InvalidArgumentException(sprintf('Free character must be one character, "%s" given.', $freeChar));
        }

        $input = clone $this;
        $input->freeChar = $freeChar;

        return $input;
    }

    public function customer(): string
    {
        return $this->customer;
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function isCreditNote(): bool
    {
        return $this->creditNote;
    }

    /**
     * Total including VAT, signed: positive when the customer owes it (invoice), negative for a
     * credit note. The total line gets this amount, debit when positive, credit when negative.
     */
    public function total(): Amount
    {
        return $this->linesTotal();
    }

    protected function lineMethods(): string
    {
        return 'revenue() or line()';
    }

    protected function appendHeader(XmlBuilder $xml, DOMElement $header): void
    {
        TransactionXmlWriter::add($xml, $header, 'duedate', TransactionXmlWriter::date($this->dueDate));
        $xml->add(
            $header,
            'invoicenumber',
            $this->invoiceNumber,
            $this->duplicateInvoiceWarning === null
                ? []
                : ['raisewarning' => XmlValue::boolean($this->duplicateInvoiceWarning)],
        );
        // The documentation: leave the element out when there is no payment reference.
        TransactionXmlWriter::add($xml, $header, 'paymentreference', $this->paymentReference);
    }

    protected function appendTotalLine(XmlBuilder $xml, DOMElement $lines): void
    {
        $total = $this->total();

        $line = $xml->add($lines, 'line', null, ['type' => LineType::Total->value, 'id' => '1']);
        TransactionXmlWriter::add($xml, $line, 'dim1', $this->account);
        $xml->add($line, 'dim2', $this->customer);
        $xml->add($line, 'debitcredit', ($total->isNegative() ? DebitCredit::Credit : DebitCredit::Debit)->value);
        $xml->add($line, 'value', $total->absolute()->value);
        TransactionXmlWriter::add($xml, $line, 'description', $this->description);
        TransactionXmlWriter::add($xml, $line, 'freechar', $this->freeChar);
        TransactionXmlWriter::add($xml, $line, 'comment', $this->comment);
    }
}
