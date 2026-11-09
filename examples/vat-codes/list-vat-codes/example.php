<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Optional search pattern from the command line, e.g. `V*`.
$pattern = $argv[1] ?? '*';

foreach ($twinfield->vatCodes()->search($pattern)->all() as $vatCode) {
    echo $vatCode->code, '  ', $vatCode->name, PHP_EOL;
}
