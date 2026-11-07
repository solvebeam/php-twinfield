<?php

declare(strict_types=1);

use SolveBeam\Twinfield\CostCenter\CostCenterInput;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$costCenters = $twinfield->costCenters();
$code = $argv[1] ?? Example::unusedCostCenterCode($twinfield);

try {
    $costCenter = $costCenters->create(CostCenterInput::create($code, 'Example cost center'));
} catch (DimensionAlreadyExistsException $exception) {
    echo 'Cost center ', $exception->dimensionCode, ' already exists: use update().', PHP_EOL;

    exit(1);
}

echo 'Created ', $costCenter->code, ' ', $costCenter->name, ' (', $costCenter->uid, ')', PHP_EOL;

// Clean up: delete the example cost center again.
$costCenters->delete($costCenter->code);

echo 'Deleted ', $costCenter->code, PHP_EOL;
