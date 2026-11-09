<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `INK 202600021 1 50.00`: day book, transaction number, line id and the value to block.
[, $dayBook, $number, $line, $value] = array_pad($argv ?? [], 5, null);

if ($dayBook === null || $number === null || $line === null || $value === null) {
    echo 'Usage: php example.php <day book> <transaction number> <line id> <value>', PHP_EOL;

    exit(0);
}

try {
    $twinfield->transactionBlockedValues()->register($dayBook, (int) $number, (int) $line, Amount::fromString($value));
} catch (CommandQueryFaultException $exception) {
    echo 'Refused (', $exception->errorCode, '): ', $exception->errorMessage, PHP_EOL;

    exit(1);
}

echo 'Blocked ', $value, ' on line ', $line, ' of ', $dayBook, ' ', $number, PHP_EOL;
