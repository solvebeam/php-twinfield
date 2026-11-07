<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// A pattern from the command line, e.g. `U*`, or else all currencies.
$search = $twinfield->currencies()->search($argv[1] ?? '*');

echo $search->count(), ' currencies', PHP_EOL;

foreach ($search->all() as $currency) {
    echo $currency->code, '  ', $currency->name, PHP_EOL;
}
