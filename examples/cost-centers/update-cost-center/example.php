<?php

declare(strict_types=1);

use SolveBeam\Twinfield\CostCenter\CostCenterInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$costCenters = $twinfield->costCenters();
$code = $argv[1] ?? Example::unusedCostCenterCode($twinfield);

$costCenters->create(CostCenterInput::create($code, 'Example cost center'));

try {
    $costCenter = $costCenters->get($code);
    echo 'Before: ', $costCenter->name, PHP_EOL;

    $costCenter = $costCenters->update($costCenter->edit()->name('Example cost center (updated)'));
    echo 'After:  ', $costCenter->name, PHP_EOL;
} finally {
    $costCenters->delete($code);
}
