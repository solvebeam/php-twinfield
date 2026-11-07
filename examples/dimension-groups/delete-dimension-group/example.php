<?php

declare(strict_types=1);

use SolveBeam\Twinfield\DimensionGroup\DimensionGroupInput;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupNotFoundException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$groups = $twinfield->dimensionGroups();

// The code from the command line, or else a temporary group to delete.
$code = $argv[1] ?? $groups->create(
    DimensionGroupInput::create(Example::unusedDimensionGroupCode($twinfield), 'Example group to delete'),
)->code;

try {
    $groups->delete($code);
} catch (DimensionGroupNotFoundException) {
    echo 'Dimension group ', $code, ' not found.', PHP_EOL;

    exit(1);
}

echo 'Deleted ', $code, PHP_EOL;
echo 'Exists:  ', $groups->exists($code) ? 'yes' : 'no', PHP_EOL;
