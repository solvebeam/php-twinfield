<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DateTimeImmutable;
use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\VatCode\VatCode;

/**
 * What a sales and a purchase transaction to create or update have in common: an invoice number,
 * a due date, a payment reference and a total line with the customer or supplier (`dim2`),
 * calculated from the lines. Immutable: every method returns a new input.
 *
 * The total line takes the other side of the lines, so the transaction always balances: debit
 * when the lines are credit on balance (a sales invoice, a purchase credit note), credit when
 * they are debit (a purchase invoice, a sales credit note).
 */
abstract class AbstractInvoiceTransactionInput extends AbstractTransactionInput
{
    private ?string $account = null;

    private ?DateTimeImmutable $dueDate = null;

    private ?string $paymentReference = null;

    private ?bool $duplicateInvoiceWarning = null;

    private ?string $description = null;

    private ?string $comment = null;

    private ?string $freeChar = null;

    final protected function __construct(
        string $dayBook,
        private readonly string $dim2,
        private readonly string $invoiceNumber,
        DateTimeImmutable $date,
        private readonly bool $creditNote,
    ) {
        parent::__construct($dayBook, $date);

        TransactionXmlWriter::dimension(sprintf('%s (dim2 of the total line)', static::dim2Name()), $dim2);

        if ($invoiceNumber === '') {
            throw new InvalidArgumentException(sprintf('A %s transaction needs an invoice number.', static::kind()));
        }

        XmlValue::maxLength('invoice number', $invoiceNumber, 40);
    }

    /**
     * Due date of the invoice, e.g. the invoice date plus the payment term.
     */
    public function dueDate(?DateTimeInterface $date): static
    {
        $input = clone $this;
        $input->dueDate = $date === null ? null : DateTimeImmutable::createFromInterface($date);

        return $input;
    }

    /**
     * Payment reference, at most 50 characters, e.g. a structured reference `+++100/0160/01495+++`.
     */
    public function paymentReference(?string $reference): static
    {
        $input = clone $this;
        $input->paymentReference = TransactionXmlWriter::optionalText('payment reference', $reference, 50);

        return $input;
    }

    /**
     * No warning when the invoice number is already used (`invoicenumber/@raisewarning`).
     */
    public function ignoreDuplicateInvoiceWarning(): static
    {
        $input = clone $this;
        $input->duplicateInvoiceWarning = false;

        return $input;
    }

    /**
     * Description of the total line, at most 40 characters.
     */
    public function description(?string $description): static
    {
        $input = clone $this;
        $input->description = TransactionXmlWriter::optionalText('total line description', $description, 40);

        return $input;
    }

    /**
     * Comment on the total line, at most 255 characters.
     */
    public function comment(?string $comment): static
    {
        $input = clone $this;
        $input->comment = TransactionXmlWriter::optionalText('total line comment', $comment, 255);

        return $input;
    }

    /**
     * Free character field of the total line: one character. `N` leaves the invoice out of the
     * direct debit runs (sales) or payment runs (purchase) of Twinfield.
     */
    public function freeChar(?string $freeChar): static
    {
        $freeChar = $freeChar === '' ? null : $freeChar;

        if ($freeChar !== null && mb_strlen($freeChar) !== 1) {
            throw new InvalidArgumentException(sprintf('Free character must be one character, "%s" given.', $freeChar));
        }

        $input = clone $this;
        $input->freeChar = $freeChar;

        return $input;
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
     * `dim2` of the total line: the customer or supplier code.
     */
    protected function dim2(): string
    {
        return $this->dim2;
    }

    /**
     * `dim1` of the total line: the accounts receivable or payable account. Without it, Twinfield
     * takes the account of the customer or supplier.
     */
    protected function withAccount(string $name, ?string $account): static
    {
        if ($account !== null) {
            TransactionXmlWriter::dimension(sprintf('%s (dim1 of the total line)', $name), $account);
        }

        $input = clone $this;
        $input->account = $account;

        return $input;
    }

    /**
     * Adds a detail line without VAT on the side of an invoice line (lineSide()), or the other
     * side for a credit note. With a VatCode and no VAT amount, the VAT is calculated with the
     * percentage of the VAT code on the transaction date.
     *
     * @param Amount|numeric-string $amount
     * @param Amount|numeric-string|null $vatValue
     */
    protected function invoiceLine(
        string $account,
        Amount|string $amount,
        string|VatCode|null $vatCode,
        Amount|string|null $vatValue,
        ?string $costCenter,
        ?string $project,
        ?string $description,
    ): static {
        $amount = Amount::of($amount);

        if ($vatCode instanceof VatCode) {
            $vatValue ??= $vatCode->vatOn($amount, $this->date());
            $vatCode = $vatCode->code;
        }

        $side = static::lineSide();

        if ($this->creditNote) {
            $side = $side === DebitCredit::Debit ? DebitCredit::Credit : DebitCredit::Debit;
        }

        $line = DetailLineInput::create($side, $account, $amount)
            ->costCenter($costCenter)
            ->project($project)
            ->description($description)
            ->vat($vatCode, $vatValue);

        return $this->line($line);
    }

    /**
     * `sales` or `purchase`, for messages.
     */
    abstract protected static function kind(): string;

    /**
     * `customer` or `supplier`, for messages.
     */
    abstract protected static function dim2Name(): string;

    /**
     * Side of a detail line on an invoice: credit for sales, debit for purchase.
     */
    abstract protected static function lineSide(): DebitCredit;

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
        // Credit positive: the total line takes the other side.
        $total = $this->linesTotal();

        $line = $xml->add($lines, 'line', null, ['type' => LineType::Total->value, 'id' => '1']);
        TransactionXmlWriter::add($xml, $line, 'dim1', $this->account);
        $xml->add($line, 'dim2', $this->dim2);
        $xml->add($line, 'debitcredit', ($total->isNegative() ? DebitCredit::Credit : DebitCredit::Debit)->value);
        $xml->add($line, 'value', $total->absolute()->value);
        TransactionXmlWriter::add($xml, $line, 'description', $this->description);
        TransactionXmlWriter::add($xml, $line, 'freechar', $this->freeChar);
        TransactionXmlWriter::add($xml, $line, 'comment', $this->comment);
    }
}
