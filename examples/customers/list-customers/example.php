<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// All customers, or those matching the pattern from the command line, e.g. "*B.V.*".
$search = $twinfield->customers()->search($argv[1] ?? '*');

echo 'Customers: ', $search->count(), PHP_EOL;

foreach ($search->all() as $customer) {
    echo $customer->code, ' ', $customer->name, PHP_EOL;
}
