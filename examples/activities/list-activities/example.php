<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

foreach ($twinfield->activities()->search()->all() as $activity) {
    echo $activity->code, '  ', $activity->name, PHP_EOL;
}
