<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BankBook\BankBookNotFoundException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first bank book.
$code = $argv[1] ?? $twinfield->bankBooks()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no bank books.' . PHP_EOL);

try {
    $bankBook = $twinfield->bankBooks()->get($code);
} catch (BankBookNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$account = $bankBook->bankAccount;
$processing = $bankBook->automaticBankProcessing;

echo 'Code:            ', $bankBook->code, PHP_EOL;
echo 'Name:            ', $bankBook->name, PHP_EOL;
echo 'Short name:      ', $bankBook->shortName ?? '-', PHP_EOL;
echo 'Ledger account:  ', $bankBook->generalLedgerAccount ?? '-', PHP_EOL;
echo 'Account holder:  ', $account->name ?? '-', PHP_EOL;
echo 'Account number:  ', $account->number ?? '-', PHP_EOL;
echo 'IBAN:            ', $account->iban ?? '-', PHP_EOL;
echo 'BIC:             ', $account->bic ?? '-', PHP_EOL;
echo 'Country:         ', $account->country ?? '-', PHP_EOL;
echo 'Automatic:       ', $processing->signUpState->value, PHP_EOL;
echo '  statements:    ', $processing->processStatements ? 'yes' : 'no', PHP_EOL;
echo '  payments:      ', $processing->processPayments ? 'yes' : 'no', PHP_EOL;
echo '  direct debits: ', $processing->processDirectDebits ? 'yes' : 'no', PHP_EOL;
