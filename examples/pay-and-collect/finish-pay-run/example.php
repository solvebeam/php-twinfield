<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\PayAndCollect\PayAndCollectException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `SEPANLCT 12`: deletes run 12 when it is not posted yet, or ends it after its files were created.
[, $payType, $id] = array_pad($argv ?? [], 3, null);

if ($payType === null || $id === null) {
    echo 'Usage: php example.php <pay type> <run ID>', PHP_EOL;

    exit(0);
}

$payAndCollect = $twinfield->payAndCollect();
$run = $payAndCollect->get($payType, (int) $id);

try {
    $payAndCollect->finish($run);
} catch (PayAndCollectException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Finished ', $run->payType, ' ', $run->id, ' (was ', $run->phase->name, ' ', $run->status->name, ')', PHP_EOL;
