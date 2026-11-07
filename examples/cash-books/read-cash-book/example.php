<?php

declare(strict_types=1);

use SolveBeam\Twinfield\CashBook\CashBookNotFoundException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first cash book.
$code = $argv[1] ?? $twinfield->cashBooks()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no cash books.' . PHP_EOL);

try {
    $cashBook = $twinfield->cashBooks()->get($code);
} catch (CashBookNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo 'Code:            ', $cashBook->code, PHP_EOL;
echo 'Name:            ', $cashBook->name, PHP_EOL;
echo 'Short name:      ', $cashBook->shortName ?? '-', PHP_EOL;
echo 'Ledger account:  ', $cashBook->generalLedgerAccount ?? '-', PHP_EOL;
