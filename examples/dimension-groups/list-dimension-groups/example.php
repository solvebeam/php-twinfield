<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// A pattern from the command line, e.g. `NL*`, or else all groups.
$search = $twinfield->dimensionGroups()->search($argv[1] ?? '*');

// Optionally only the groups of one dimension type, e.g. `DEB` for customers.
if (isset($argv[2])) {
    $search = $search->dimensionType($argv[2]);
}

echo $search->count(), ' dimension groups', PHP_EOL;

foreach ($search->all() as $group) {
    echo $group->code, '  ', $group->name, PHP_EOL;
}
