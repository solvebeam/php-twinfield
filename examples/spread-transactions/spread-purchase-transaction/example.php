<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;
use SolveBeam\Twinfield\Transaction\TransactionStatus;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `INK 202600021 2500 2026/01 2026/12`: a purchase transaction, the intermediate account
// and the first and last period of the spread.
[, $dayBook, $number, $intermediateAccount, $start, $end] = array_pad($argv ?? [], 6, null);

if ($dayBook === null || $number === null || $intermediateAccount === null || $start === null || $end === null) {
    echo 'Usage: php example.php <day book> <transaction number> <intermediate account> ',
        '<start period> <end period>', PHP_EOL;

    exit(0);
}

$spreads = $twinfield->spreadTransactions();

try {
    $transaction = $twinfield->purchaseTransactions()->get($dayBook, (int) $number);
    $existing = $spreads->get($dayBook, (int) $number);

    if ($existing->isSpread()) {
        echo 'Already spread in ', count($existing->spreadTransactions), ' transactions.', PHP_EOL;

        exit(0);
    }

    $from = Period::fromString($start);
    $to = Period::fromString($end);

    // A final transaction is spread with postFinal(), a provisional one with postProvisional().
    $spread = $transaction->header->status === TransactionStatus::Final
        ? $spreads->postFinal($dayBook, (int) $number, $intermediateAccount, $from, $to)
        : $spreads->postProvisional($dayBook, (int) $number, $intermediateAccount, $from, $to);
} catch (TransactionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Spread ', $dayBook, ' ', $number, ' (', $transaction->value(), ' ', $transaction->header->currency, ') over ',
    $start, ' to ', $end, ' through ', $intermediateAccount, PHP_EOL;

foreach ($spread->spreadTransactions as $spreadTransaction) {
    echo 'Spread transaction: ', $spreadTransaction->dayBook, ' ', $spreadTransaction->number, PHP_EOL;
}
