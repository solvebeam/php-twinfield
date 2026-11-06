<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Activity\ActivityInput;
use SolveBeam\Twinfield\Dimension\ChangeMode;
use SolveBeam\Twinfield\Dimension\Quantity;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$activities = $twinfield->activities();
$code = $argv[1] ?? Example::unusedActivityCode($twinfield);

$activity = $activities->create(
    ActivityInput::create($code, 'Example activity')
        ->shortName('Example')
        ->invoiceDescription('Created by the php-twinfield examples')
        ->billable(true, ChangeMode::Disallow)
        ->quantity(Quantity::create('KM')->billable(false)),
);

echo 'Created ', $activity->code, ' ', $activity->name, ' (', $activity->uid, ')', PHP_EOL;

// Clean up: delete the example activity again.
$activities->delete($activity->code);

echo 'Deleted ', $activity->code, PHP_EOL;
