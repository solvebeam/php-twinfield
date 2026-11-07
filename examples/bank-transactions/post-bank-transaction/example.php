<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

if (!isset($argv[1], $argv[2])) {
    echo 'Pass a bank book and the number of a provisional transaction, e.g. BNK 202600045.', PHP_EOL;
    echo 'Posting cannot be undone: a final transaction cannot be changed or deleted.', PHP_EOL;

    exit(0);
}

$bank = $twinfield->bankTransactions();
$transaction = $bank->get($argv[1], (int) $argv[2]);

if (!$transaction->header->isProvisional()) {
    echo $transaction->dayBook(), ' ', $transaction->number(), ' is already final.', PHP_EOL;

    exit(0);
}

$bank->post($transaction->dayBook(), $transaction->number());

echo 'Posted ', $transaction->dayBook(), ' ', $transaction->number(), ': ',
    $bank->get($transaction->dayBook(), $transaction->number())->header->status->name, PHP_EOL;
