<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CashTransaction;

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
}
