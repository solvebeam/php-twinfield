<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first cost center.
$code = $argv[1] ?? $twinfield->costCenters()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no cost centers.' . PHP_EOL);

try {
    $costCenter = $twinfield->costCenters()->get($code);
} catch (DimensionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo 'Code:       ', $costCenter->code, PHP_EOL;
echo 'Name:       ', $costCenter->name, PHP_EOL;
echo 'UID:        ', $costCenter->uid, PHP_EOL;
echo 'Status:     ', $costCenter->status->value, $costCenter->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Behaviour:  ', $costCenter->behaviour->value, PHP_EOL;
echo 'Modified:   ', $costCenter->modified?->format('Y-m-d H:i') ?? '-', PHP_EOL;
echo 'Changes:    ', $costCenter->touched, PHP_EOL;
