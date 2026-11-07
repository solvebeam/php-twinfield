<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$cashBooks = $twinfield->cashBooks();
$original = $cashBooks->get($argv[1] ?? 'EXAMPLE');

echo 'Before: ', $original->name, ', short name ', $original->shortName ?? '-', PHP_EOL;

try {
    $cashBook = $cashBooks->update(
        $original->edit()
            ->name($original->name . ' (updated)')
            ->shortName(null),
    );

    echo 'After:  ', $cashBook->name, ', short name ', $cashBook->shortName ?? '-', PHP_EOL;
} finally {
    $cashBooks->update($original->edit());
}
