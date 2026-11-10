<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesTransaction;

use DateTimeImmutable;
use DateTimeInterface;
use SolveBeam\Twinfield\Transaction\AbstractInvoiceTransactionInput;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DebitCredit;
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
final class SalesTransactionInput extends AbstractInvoiceTransactionInput
{
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
     * @param string $dim1 The profit and loss account, e.g. `8000`.
     * @param Amount|numeric-string $amount Amount without VAT.
     * @param Amount|numeric-string|null $vatValue VAT amount; Twinfield refuses the transaction when it differs.
     * @param ?string $costCenter `dim2`: cost center.
     * @param ?string $project `dim3`: project or asset.
     */
    public function revenue(
        string $dim1,
        Amount|string $amount,
        string|VatCode|null $vatCode = null,
        Amount|string|null $vatValue = null,
        ?string $costCenter = null,
        ?string $project = null,
        ?string $description = null,
    ): self {
        return $this->invoiceLine($dim1, $amount, $vatCode, $vatValue, $costCenter, $project, $description);
    }

    /**
     * `dim1` of the total line: the accounts receivable account, e.g. `1300`. Without it,
     * Twinfield takes the account of the customer.
     */
    public function receivablesAccount(?string $dim1): self
    {
        return $this->withDim1($dim1);
    }

    /**
     * Leave the invoice out of the direct debit runs of Twinfield (`freechar` `N` on the total line).
     */
    public function excludeFromDirectDebit(bool $exclude = true): self
    {
        return $this->freeChar($exclude ? 'N' : null);
    }

    public function customer(): string
    {
        return $this->dim2();
    }

    /**
     * Total including VAT, signed: positive when the customer owes it (invoice), negative for a
     * credit note. The total line gets this amount, debit when positive, credit when negative.
     */
    public function total(): Amount
    {
        return $this->linesTotal();
    }

    protected static function kind(): string
    {
        return 'sales';
    }

    protected static function lineSide(): DebitCredit
    {
        return DebitCredit::Credit;
    }

    protected function lineMethods(): string
    {
        return 'revenue() or line()';
    }
}
