<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Optional search pattern from the command line, e.g. `MACH*`.
$pattern = $argv[1] ?? '*';

foreach ($twinfield->classicAssetMethods()->search($pattern)->all() as $method) {
    echo $method->code, '  ', $method->name, PHP_EOL;
}
