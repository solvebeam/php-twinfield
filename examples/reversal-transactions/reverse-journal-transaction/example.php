<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `MEMO 202600003`: day book and number of a final journal transaction.
[, $dayBook, $number] = array_pad($argv ?? [], 3, null);

if ($dayBook === null || $number === null) {
    echo 'Usage: php example.php <day book> <transaction number>', PHP_EOL;

    exit(0);
}

$reversals = $twinfield->reversalTransactions();

try {
    $existing = $reversals->get($dayBook, (int) $number);

    if ($existing->reversed !== null) {
        echo 'Already reversed by ', $existing->reversed->dayBook, ' ', $existing->reversed->number, PHP_EOL;

        exit(0);
    }

    $reversed = $reversals->postFinal($dayBook, (int) $number);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Reversed ', $dayBook, ' ', $number, ' by ', $reversed->dayBook, ' ', $reversed->number, PHP_EOL;

$transaction = $twinfield->journalTransactions()->get($reversed->dayBook, $reversed->number);

echo 'Debit ', $transaction->debit(), ', credit ', $transaction->credit(), PHP_EOL;
