<?php

declare(strict_types=1);

use SolveBeam\Twinfield\DimensionGroup\DimensionGroupInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$groups = $twinfield->dimensionGroups();
$code = $argv[1] ?? Example::unusedDimensionGroupCode($twinfield);

// Three existing customers (dimension type DEB) as members.
$customers = Example::customerCodes($twinfield, 3);

$group = $groups->create(
    DimensionGroupInput::create($code, 'Example key accounts')
        ->shortName('Key accounts')
        ->add('DEB', ...$customers),
);

try {
    echo 'Created ', $group->code, ' (', $group->name, ')', PHP_EOL;

    foreach ($group->dimensions as $dimension) {
        echo '  ', $dimension->type, ' ', $dimension->code, '  ', $dimension->name ?? '-', PHP_EOL;
    }
} finally {
    // Clean up.
    $groups->delete($code);
}
