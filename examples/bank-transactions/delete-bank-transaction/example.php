<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BankTransaction\BankTransactionInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$bank = $twinfield->bankTransactions();

$transaction = $bank->create(
    BankTransactionInput::forBankBook(Example::bankBook($twinfield), new DateTimeImmutable())
        ->pay(Example::profitAndLossAccount($twinfield), '1.00', description: 'Entered twice'),
);

// Only provisional transactions can be deleted; Twinfield wants a reason.
$bank->delete($transaction->dayBook(), $transaction->number(), 'Entered twice');

echo 'Deleted ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
echo 'Still there: ', $bank->find($transaction->dayBook(), $transaction->number()) === null ? 'no' : 'yes', PHP_EOL;
