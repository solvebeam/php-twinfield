<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$runs = $twinfield->payAndCollect()->list();

if ($runs === []) {
    echo 'No pay runs in company ', $twinfield->companyCode(), '.', PHP_EOL;

    exit;
}

foreach ($runs as $run) {
    echo $run->payType, ' ', $run->id, ': ', $run->runType->name, ', ', $run->phase->name, ' ', $run->status->name,
        ', pay date ', $run->payDate->format('Y-m-d'), ', period ', $run->period, ', bank ', $run->bank, PHP_EOL;
}
