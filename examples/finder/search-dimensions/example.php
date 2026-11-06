<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Finder\FinderType;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$debtors = $twinfield->finder(FinderType::Dimensions)
    ->pattern('*')
    ->option('dimtype', 'DEB')
    ->limit(10)
    ->get();

echo "{$debtors->totalRows} debtors, first ", count($debtors), ':', PHP_EOL;

foreach ($debtors as [$code, $name]) {
    echo "{$code} {$name}", PHP_EOL;
}
