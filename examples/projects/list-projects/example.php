<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

foreach ($twinfield->projects()->search()->all() as $project) {
    echo $project->code, '  ', $project->name, PHP_EOL;
}
