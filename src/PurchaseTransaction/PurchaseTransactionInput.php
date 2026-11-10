<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PurchaseTransaction;

use DateTimeImmutable;
use DateTimeInterface;
use SolveBeam\Twinfield\Transaction\AbstractInvoiceTransactionInput;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\VatCode\VatCode;

/**
 * A purchase transaction to create or update: the booking of an invoice you received from a
 * supplier. Immutable: every method returns a new input.
 *
 *   $input = PurchaseTransactionInput::invoice('INK', supplier: '2000', invoiceNumber: 'F-2026-118', date: $date)
 *       ->dueDate($date->modify('+14 days'))
 *       ->expense('7000', '100.00', vatCode: 'IH', vatValue: '21.00', description: 'Office supplies')
 *       ->expense('7010', '50.00', vatCode: $twinfield->vatCodes()->get('IL'));
 *
 *   $transaction = $twinfield->purchaseTransactions()->create($input);
 *
 * expense() books the costs without VAT: a debit line for an invoice, a credit line for a credit
 * note. The total line with the supplier and the amount including VAT is calculated from the
 * lines, so the transaction always balances. New transactions are provisional unless you call
 * final().
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/purchase-transactions/
 */
final class PurchaseTransactionInput extends AbstractInvoiceTransactionInput
{
    /**
     * A purchase invoice: expense() adds debit lines, the total line is credit.
     *
     * @param string $dayBook Purchase day book (transaction type) code, e.g. `INK`.
     * @param string $supplier Supplier code (`dim2` of the total line), e.g. `2000`.
     * @param string $invoiceNumber Invoice number of the supplier, at most 40 characters.
     * @param DateTimeInterface $date Invoice date; Twinfield derives the period from it.
     */
    public static function invoice(
        string $dayBook,
        string $supplier,
        string $invoiceNumber,
        DateTimeInterface $date,
    ): self {
        return new self($dayBook, $supplier, $invoiceNumber, DateTimeImmutable::createFromInterface($date), false);
    }

    /**
     * A credit note of the supplier: expense() adds credit lines, the total line is debit.
     * Amounts stay positive.
     *
     * @param string $dayBook Purchase day book (transaction type) code, e.g. `INK`.
     * @param string $supplier Supplier code (`dim2` of the total line), e.g. `2000`.
     * @param string $invoiceNumber Number of the credit note, at most 40 characters.
     * @param DateTimeInterface $date Date of the credit note.
     */
    public static function creditNote(
        string $dayBook,
        string $supplier,
        string $invoiceNumber,
        DateTimeInterface $date,
    ): self {
        return new self($dayBook, $supplier, $invoiceNumber, DateTimeImmutable::createFromInterface($date), true);
    }

    /**
     * Costs without VAT: a debit line for an invoice, a credit line for a credit note.
     *
     *   ->expense('7000', '100.00', vatCode: 'IH', vatValue: '21.00')
     *   ->expense('7000', '100.00', vatCode: $twinfield->vatCodes()->get('IH'))
     *
     * With a VatCode and no VAT amount, the VAT is calculated with the percentage of the VAT
     * code on the transaction date. With a VAT code as string, give the VAT amount too.
     *
     * @param string $dim1 The profit and loss account, e.g. `7000`, or a balance sheet
     *                     account, e.g. for stock.
     * @param Amount|numeric-string $amount Amount without VAT.
     * @param Amount|numeric-string|null $vatValue VAT amount; Twinfield refuses the transaction when it differs.
     * @param ?string $costCenter `dim2`: cost center.
     * @param ?string $project `dim3`: project or asset.
     */
    public function expense(
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
     * `dim1` of the total line: the accounts payable account, e.g. `1600`. Without it,
     * Twinfield takes the account of the supplier.
     */
    public function payablesAccount(?string $dim1): self
    {
        return $this->withDim1('payables account', $dim1);
    }

    /**
     * Leave the invoice out of the payment runs of Twinfield (`freechar` `N` on the total line).
     */
    public function excludeFromPaymentRuns(bool $exclude = true): self
    {
        return $this->freeChar($exclude ? 'N' : null);
    }

    public function supplier(): string
    {
        return $this->dim2();
    }

    /**
     * Total including VAT, signed: positive when you owe it to the supplier (invoice), negative
     * for a credit note. The total line gets this amount, credit when positive, debit when negative.
     */
    public function total(): Amount
    {
        return $this->linesTotal()->negate();
    }

    protected static function kind(): string
    {
        return 'purchase';
    }

    protected static function dim2Name(): string
    {
        return 'supplier';
    }

    protected static function lineSide(): DebitCredit
    {
        return DebitCredit::Debit;
    }

    protected function lineMethods(): string
    {
        return 'expense() or line()';
    }
}
