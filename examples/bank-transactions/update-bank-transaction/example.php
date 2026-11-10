<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BankTransaction\BankTransactionInput;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\DetailLineInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$bank = $twinfield->bankTransactions();
$costs = Example::profitAndLossAccount($twinfield);

$created = $bank->create(
    BankTransactionInput::forBankBook(Example::bankBook($twinfield), new DateTimeImmutable())
        ->pay($costs, '12.50', description: 'Bank costs'),
);

echo 'Created: ', $created->number(), ' ', $created->details[0]->description, ' ', $created->balance(), PHP_EOL;

// Start from edit(), so all current values are kept; change the line and add one.
$updated = $bank->update(
    $created->edit()
        ->mapLines(static fn (DetailLineInput $line): DetailLineInput => $line->description('Bank costs October'))
        ->pay($costs, '2.50', description: 'Bank costs November'),
);

echo 'Updated: ', $updated->number(), ' ', count($updated->details), ' lines, ', $updated->balance(), PHP_EOL;

foreach ($updated->details as $line) {
    echo '  ', $line->description, ': ', $line->value, PHP_EOL;
}

// Clean up: delete the provisional example transaction again.
$bank->delete($updated->dayBook(), $updated->number(), 'php-twinfield example');
