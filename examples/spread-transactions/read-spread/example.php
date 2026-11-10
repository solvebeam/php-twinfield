<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `INK 202600021`: day book and number of a sales or purchase transaction.
[, $dayBook, $number] = array_pad($argv ?? [], 3, null);

if ($dayBook === null || $number === null) {
    echo 'Usage: php example.php <day book> <transaction number>', PHP_EOL;

    exit(0);
}

try {
    $spread = $twinfield->spreadTransactions()->get($dayBook, (int) $number);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Original: ', $spread->original->dayBook, ' ', $spread->original->number, PHP_EOL;

if ($spread->settings === null) {
    echo 'Not spread.', PHP_EOL;

    exit(0);
}

echo 'Intermediate account: ', $spread->settings->intermediateAccount, PHP_EOL;

if ($spread->settings->isByLines()) {
    foreach ($spread->settings->lines as $line) {
        echo 'Line ', $line->lineId, ': ', $line->startPeriod, ' to ', $line->endPeriod, PHP_EOL;
    }
} else {
    echo 'Periods: ', $spread->settings->startPeriod, ' to ', $spread->settings->endPeriod, PHP_EOL;
}

foreach ($spread->spreadTransactions as $transaction) {
    echo 'Spread transaction: ', $transaction->dayBook, ' ', $transaction->number, PHP_EOL;
}
