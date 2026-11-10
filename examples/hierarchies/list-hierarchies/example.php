<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Optional search pattern from the command line, e.g. `RGS*`.
$pattern = $argv[1] ?? '*';

foreach ($twinfield->hierarchies()->search($pattern)->all() as $hierarchy) {
    echo $hierarchy->code, '  ', $hierarchy->name, PHP_EOL;
}
