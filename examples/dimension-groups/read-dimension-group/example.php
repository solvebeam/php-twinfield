<?php

declare(strict_types=1);

use SolveBeam\Twinfield\DimensionGroup\DimensionGroupNotFoundException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first dimension group.
$code = $argv[1] ?? $twinfield->dimensionGroups()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no dimension groups.' . PHP_EOL);

try {
    $group = $twinfield->dimensionGroups()->get($code);
} catch (DimensionGroupNotFoundException) {
    echo 'Dimension group ', $code, ' not found.', PHP_EOL;

    exit(1);
}

echo 'Code:        ', $group->code, PHP_EOL;
echo 'Name:        ', $group->name, PHP_EOL;
echo 'Short name:  ', $group->shortName ?? '-', PHP_EOL;
echo 'Status:      ', $group->status->value, PHP_EOL;
echo 'Types:       ', implode(', ', $group->types()) ?: '-', PHP_EOL;
echo PHP_EOL;

echo count($group->dimensions), ' dimensions:', PHP_EOL;

foreach ($group->dimensions as $dimension) {
    echo '  ', $dimension->type, ' ', $dimension->code, '  ', $dimension->name ?? '-', PHP_EOL;
}
