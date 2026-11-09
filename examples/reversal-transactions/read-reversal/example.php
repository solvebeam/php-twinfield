<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `MEMO 202600003`: day book and number of a journal transaction.
[, $dayBook, $number] = array_pad($argv ?? [], 3, null);

if ($dayBook === null || $number === null) {
    echo 'Usage: php example.php <day book> <transaction number>', PHP_EOL;

    exit(0);
}

try {
    $reversal = $twinfield->reversalTransactions()->get($dayBook, (int) $number);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Original:               ', $reversal->original->dayBook, ' ', $reversal->original->number, PHP_EOL;
echo 'Reversed:               ', $reversal->reversed === null
    ? '-'
    : $reversal->reversed->dayBook . ' ' . $reversal->reversed->number, PHP_EOL;
echo 'Reverse in next period: ', $reversal->reverseInNextPeriod ? 'yes' : 'no', PHP_EOL;
