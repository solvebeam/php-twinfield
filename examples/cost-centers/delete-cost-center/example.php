<?php

declare(strict_types=1);

use SolveBeam\Twinfield\CostCenter\CostCenterInput;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$costCenters = $twinfield->costCenters();
$code = $argv[1] ?? Example::unusedCostCenterCode($twinfield);

$costCenters->create(CostCenterInput::create($code, 'Example cost center'));

$result = $costCenters->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;

echo 'Status now: ', $costCenters->get($code)->status->value, PHP_EOL;

// A deleted or hidden cost center can be made active again.
$costCenters->activate($code);

echo 'After activate(): ', $costCenters->get($code)->status->value, PHP_EOL;

// Clean up.
$costCenters->delete($code);
