<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// All suppliers, or those matching the pattern from the command line, e.g. "*B.V.*".
$search = $twinfield->suppliers()->search($argv[1] ?? '*');

echo 'Suppliers: ', $search->count(), PHP_EOL;

foreach ($search->all() as $supplier) {
    echo $supplier->code, ' ', $supplier->name, PHP_EOL;
}
