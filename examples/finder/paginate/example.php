<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Finder\FinderType;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$count = 0;

// all() fetches the next page only when you get there.
foreach ($twinfield->finder(FinderType::Countries)->limit(100)->all() as [$code, $name]) {
    if (++$count <= 5) {
        echo "{$code} {$name}", PHP_EOL;
    }
}

echo "… {$count} countries in total, fetched in pages of 100", PHP_EOL;
