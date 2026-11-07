<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Optional search pattern from the command line, e.g. `2025*`.
$pattern = $argv[1] ?? '*';

foreach ($twinfield->budgets()->search($pattern)->all() as $budget) {
    echo $budget->code, '  ', $budget->name, PHP_EOL;
}
