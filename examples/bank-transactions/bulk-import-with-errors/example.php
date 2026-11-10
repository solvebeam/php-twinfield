<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BankTransaction\BankTransactionInput;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\SavedTransaction;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$bank = $twinfield->bankTransactions();
$costs = Example::profitAndLossAccount($twinfield);
$base = BankTransactionInput::forBankBook(Example::bankBook($twinfield), new DateTimeImmutable());

// Three statements in one request; the second has an account that does not exist.
$result = $bank->saveMany(
    $base->statement(1)->pay($costs, '1.00', description: 'Example day 1'),
    $base->statement(2)->pay('NOPE9999', '2.00', description: 'Example day 2'),
    $base->statement(3)->pay($costs, '3.00', description: 'Example day 3'),
);

foreach ($result as $item) {
    echo sprintf('[%d/%d] ', $item->index + 1, count($result)), match (true) {
        $item instanceof SavedTransaction => 'saved as ' . $item->transaction->number(),
        default => 'failed: ' . $item->message(),
    }, PHP_EOL;
}

echo count($result->saved()), ' saved, ', count($result->failed()), ' failed', PHP_EOL;

// Clean up: delete the provisional example transactions again.
foreach ($result->saved() as $saved) {
    $bank->delete($saved->transaction->dayBook(), $saved->transaction->number(), 'twinfield-php example');
}
