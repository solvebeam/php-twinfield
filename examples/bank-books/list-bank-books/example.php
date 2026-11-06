<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

foreach ($twinfield->bankBooks()->search($argv[1] ?? '*')->all() as $bankBook) {
    echo $bankBook->code, '  ', $bankBook->name, PHP_EOL;
}
