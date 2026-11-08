<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Optional search pattern from the command line, e.g. `T*`.
$pattern = $argv[1] ?? '*';

foreach ($twinfield->rates()->search($pattern)->all() as $rate) {
    echo $rate->code, '  ', $rate->name, PHP_EOL;
}
