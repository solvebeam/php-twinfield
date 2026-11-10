<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CashTransaction;

use SolveBeam\Twinfield\Transaction\StatementTransaction;

/**
 * A cash transaction as Twinfield returns it: one cash statement.
 *
 *   $transaction = $twinfield->cashTransactions()->get('KAS', 202600045);
 *
 *   echo $transaction->startValue, ' → ', $transaction->closeValue, PHP_EOL;
 *
 *   foreach ($transaction->details as $line) {
 *       echo $line->debitCredit->value, ' ', $line->value, ' ', $line->dim2?->name, PHP_EOL;
 *   }
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/cash-transactions/
 */
final readonly class CashTransaction extends StatementTransaction
{
    public function edit(): CashTransactionInput
    {
        $input = CashTransactionInput::create($this->header->dayBook, $this->total->account->code, $this->header->date);

        return $this->fill($input);
    }
}
