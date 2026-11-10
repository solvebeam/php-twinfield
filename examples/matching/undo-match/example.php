<?php

declare(strict_types=1);

use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Matching\MatchingException;
use SolveBeam\Twinfield\Matching\MatchNotFoundException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `1000 12`: the customer and the match number, see the list-match-sets example.
[, $customer, $matchNumber] = array_pad($argv ?? [], 3, null);

if ($customer === null || $matchNumber === null) {
    echo 'Usage: php example.php <customer> <match number>', PHP_EOL;

    exit(0);
}

try {
    $twinfield->matching()->undo(DimensionTypeCode::AccountsReceivable, $customer, (int) $matchNumber);
} catch (MatchNotFoundException $exception) {
    echo 'Not found: ', $exception->messages[0]->text, PHP_EOL;

    exit(1);
} catch (MatchingException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Undid match ', $matchNumber, ' of customer ', $customer, PHP_EOL;
