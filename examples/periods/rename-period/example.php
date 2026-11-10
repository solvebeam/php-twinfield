<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Period\Period;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$periods = $twinfield->periods();
$years = $periods->years();

echo 'Company ', $twinfield->companyCode(), PHP_EOL;

if ($years === []) {
    echo 'The company has no years yet.', PHP_EOL;

    exit;
}

$period = Period::of(max($years), 1);

$original = $periods->get($period)->name;

if ($original === null) {
    echo 'Period ', $period, ' has no name to restore.', PHP_EOL;

    exit;
}

echo 'Before:   ', $original, PHP_EOL;

try {
    $periods->changeName($period, $original . ' (renamed)');
    echo 'Renamed:  ', $periods->get($period)->name, PHP_EOL;
} finally {
    $periods->changeName($period, $original);
    echo 'Restored: ', $periods->get($period)->name, PHP_EOL;
}
