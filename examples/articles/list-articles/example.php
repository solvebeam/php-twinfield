<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Optional search pattern from the command line, e.g. `BIKE*`.
$pattern = $argv[1] ?? '*';

foreach ($twinfield->articles()->search($pattern)->all() as $article) {
    echo $article->code, '  ', $article->name, PHP_EOL;
}
