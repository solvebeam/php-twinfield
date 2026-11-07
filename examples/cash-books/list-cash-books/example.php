<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

foreach ($twinfield->cashBooks()->search($argv[1] ?? '*')->all() as $cashBook) {
    echo $cashBook->code, '  ', $cashBook->name, PHP_EOL;
}
