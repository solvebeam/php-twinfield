<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `INK 202600021 1`: day book, transaction number and line id.
[, $dayBook, $number, $line] = array_pad($argv ?? [], 4, null);

if ($dayBook === null || $number === null || $line === null) {
    echo 'Usage: php example.php <day book> <transaction number> <line id>', PHP_EOL;

    exit(0);
}

try {
    $twinfield->transactionBlockedValues()->unregister($dayBook, (int) $number, (int) $line);
} catch (CommandQueryFaultException $exception) {
    echo 'Refused (', $exception->errorCode, '): ', $exception->errorMessage, PHP_EOL;

    exit(1);
}

echo 'Removed the blocked value from line ', $line, ' of ', $dayBook, ' ', $number, PHP_EOL;
