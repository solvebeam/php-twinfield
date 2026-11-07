<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BankTransaction\BankTransactionInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$bank = $twinfield->bankTransactions();
$bankBook = Example::bankBook($twinfield);
$costs = Example::profitAndLossAccount($twinfield);

$transaction = $bank->create(
    BankTransactionInput::forBankBook($bankBook, new DateTimeImmutable())
        ->statement(1, startValue: '100.00')
        ->description('Example statement')
        ->pay($costs, '12.50', description: 'Example bank costs'),
);

echo 'Created:  ', $transaction->dayBook(), ' ', $transaction->number(),
    ' (', $transaction->header->status->name, ')', PHP_EOL;
echo 'Total:    ', $transaction->total->debitCredit->value, ' ', $transaction->total->amounts->value, PHP_EOL;
echo 'Balance:  ', $transaction->startValue, ' → ', $transaction->closeValue, PHP_EOL;

// Clean up: delete the provisional example transaction again.
$bank->delete($transaction->dayBook(), $transaction->number(), 'php-twinfield example');

echo 'Deleted:  ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
