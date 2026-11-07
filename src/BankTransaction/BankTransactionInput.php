<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankTransaction;

use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\BankBook\BankBook;
use SolveBeam\Twinfield\Transaction\StatementTransactionInput;

/**
 * A bank transaction to create or update: one bank statement. Immutable: every method returns a new input.
 *
 *   $input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
 *       ->statement(245, startValue: '1000.00')
 *       ->receive('1300', '121.00', customer: '1000', invoice: '2026-0042', description: 'Invoice 2026-0042')
 *       ->pay('1600', '50.00', supplier: '2000', invoice: 'INV-778')
 *       ->pay('4500', '10.00', vatCode: 'IH', vatValue: '2.10', description: 'Bank costs');
 *
 *   $transaction = $twinfield->bankTransactions()->create($input);
 *
 * The total line on the bank account and the closing balance are calculated from the lines.
 * New transactions are provisional unless you call final().
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/bank-transactions/
 */
final class BankTransactionInput extends StatementTransactionInput
{
    /**
     * Uses the code and the ledger account of a bank book, e.g. from `$twinfield->bankBooks()->get('BNK')`.
     *
     * @throws InvalidArgumentException When the bank book has no ledger account.
     */
    public static function forBankBook(BankBook $bankBook, DateTimeInterface $date): self
    {
        $account = $bankBook->generalLedgerAccount ?? throw new InvalidArgumentException(sprintf(
            'Bank book %s has no ledger account: set one in Twinfield, or pass it to create().',
            $bankBook->code,
        ));

        return self::create($bankBook->code, $account, $date);
    }
}
