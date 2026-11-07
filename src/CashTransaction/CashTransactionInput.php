<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CashTransaction;

use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\CashBook\CashBook;
use SolveBeam\Twinfield\Transaction\StatementTransactionInput;

/**
 * A cash transaction to create or update: one cash statement, e.g. a day of the cash register.
 * Immutable: every method returns a new input.
 *
 *   $input = CashTransactionInput::create('KAS', '1000', new DateTimeImmutable('2026-11-03'))
 *       ->statement(311, startValue: '250.00')
 *       ->receive('8000', '413.22', vatCode: 'VH', vatValue: '86.78', description: 'Sales 21% VAT')
 *       ->pay('4300', '20.00', vatCode: 'IH', vatValue: '4.20', description: 'Coffee and milk');
 *
 *   $transaction = $twinfield->cashTransactions()->create($input);
 *
 * The total line on the cash account and the closing balance are calculated from the lines.
 * New transactions are provisional unless you call final().
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/cash-transactions/
 */
final class CashTransactionInput extends StatementTransactionInput
{
    /**
     * Uses the code and the ledger account of a cash book, e.g. from `$twinfield->cashBooks()->get('KAS')`.
     *
     * @throws InvalidArgumentException When the cash book has no ledger account.
     */
    public static function forCashBook(CashBook $cashBook, DateTimeInterface $date): self
    {
        $account = $cashBook->generalLedgerAccount ?? throw new InvalidArgumentException(sprintf(
            'Cash book %s has no ledger account: set one in Twinfield, or pass it to create().',
            $cashBook->code,
        ));

        return self::create($cashBook->code, $account, $date);
    }
}
