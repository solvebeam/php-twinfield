<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// A pattern from the command line, e.g. `1*`, or else all cost centers.
$search = $twinfield->costCenters()->search($argv[1] ?? '*');

echo $search->count(), ' cost centers', PHP_EOL;

foreach ($search->all() as $costCenter) {
    echo $costCenter->code, '  ', $costCenter->name, PHP_EOL;
}
