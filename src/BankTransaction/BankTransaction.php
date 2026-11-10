<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankTransaction;

use SolveBeam\Twinfield\Transaction\StatementTransaction;

/**
 * A bank transaction as Twinfield returns it: one bank statement.
 *
 *   $transaction = $twinfield->bankTransactions()->get('BNK', 202600045);
 *
 *   echo $transaction->startValue, ' → ', $transaction->closeValue, PHP_EOL;
 *
 *   foreach ($transaction->details as $line) {
 *       echo $line->debitCredit->value, ' ', $line->value, ' ', $line->dim2?->name, PHP_EOL;
 *   }
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/bank-transactions/
 */
final readonly class BankTransaction extends StatementTransaction
{
    public function edit(): BankTransactionInput
    {
        $input = BankTransactionInput::create($this->header->dayBook, $this->total->dim1->code, $this->header->date);

        return $this->fill($input);
    }
}
