<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

foreach ($twinfield->users()->list() as $user) {
    echo str_pad($user->code, 16), ' ', $user->name, PHP_EOL;
}
