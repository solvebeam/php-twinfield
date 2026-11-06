<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$bankBooks = $twinfield->bankBooks();
$original = $bankBooks->get($argv[1] ?? 'EXAMPLE');

echo 'Before: ', $original->name, ', BIC ', $original->bankAccount->bic ?? '-', PHP_EOL;

try {
    $bankBook = $bankBooks->update(
        $original->edit()
            ->name($original->name . ' (updated)')
            ->bankAccount($original->bankAccount->edit()->bic(null)),
    );

    echo 'After:  ', $bankBook->name, ', BIC ', $bankBook->bankAccount->bic ?? '-', PHP_EOL;
} finally {
    $bankBooks->update($original->edit());
}
