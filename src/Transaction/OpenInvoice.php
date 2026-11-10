<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * An open (unpaid) invoice in the results of Finder `IVT`: a sales or a purchase invoice.
 *
 * The Finder gives no day book or transaction number: use the invoice number and the customer
 * or supplier, e.g. to match a payment by invoice number.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/
 */
final readonly class OpenInvoice
{
    public function __construct(
        /** Company the Finder searched in, with its organisation. */
        public CompanyReference $company,
        public string $invoiceNumber,
        /** The open (outstanding) amount, always positive: 10.00 for a partly paid invoice of 15.00. */
        public Amount $amount,
        /** Code of the customer or supplier. */
        public string $relation,
        /** Name of the customer or supplier. */
        public string $relationName,
        /**
         * The side as the Finder gives it, the opposite of the total line of the invoice: credit
         * for a sales invoice, debit for a purchase invoice. Credit notes the other way round.
         */
        public DebitCredit $debitCredit,
    ) {
    }

    /**
     * From a Finder row; columns: invoice number, amount, customer or supplier code, name,
     * debit/credit (`D` or `C`). Column names depend on the user's language.
     *
     * @param list<string> $row
     *
     * @internal
     */
    public static function fromFinderRow(array $row, int $index, CompanyReference $company): self
    {
        if (count($row) !== 5) {
            throw self::invalid($index, $row, 'expected 5 columns');
        }

        [$invoiceNumber, $amount, $relation, $name, $side] = $row;

        if ($invoiceNumber === '' || $relation === '') {
            throw self::invalid($index, $row, 'no invoice number or relation');
        }

        if (preg_match('~^\d+(?:\.\d{1,2})?$~', $amount) !== 1) {
            throw self::invalid($index, $row, 'the amount is not a positive amount');
        }

        $debitCredit = match ($side) {
            'D' => DebitCredit::Debit,
            'C' => DebitCredit::Credit,
            default => throw self::invalid($index, $row, 'debit/credit is not D or C'),
        };

        return new self($company, $invoiceNumber, Amount::fromString($amount), $relation, $name, $debitCredit);
    }

    /**
     * @param list<string> $row
     */
    private static function invalid(int $index, array $row, string $problem): UnexpectedResponseException
    {
        return new UnexpectedResponseException(sprintf(
            'Finder open invoice row %d: %s: %s.',
            $index,
            $problem,
            json_encode($row, JSON_THROW_ON_ERROR),
        ));
    }
}
