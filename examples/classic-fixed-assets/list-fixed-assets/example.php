<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// A pattern from the command line, e.g. `6*`, or else all fixed assets.
$search = $twinfield->classicFixedAssets()->search($argv[1] ?? '*');

// Only the assets in one dimension group: $search = $search->group('VEHICLES');

echo $search->count(), ' fixed assets', PHP_EOL;

foreach ($search->all() as $asset) {
    echo $asset->code, '  ', $asset->name, PHP_EOL;
}
