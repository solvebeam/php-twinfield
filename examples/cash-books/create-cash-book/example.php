<?php

declare(strict_types=1);

use SolveBeam\Twinfield\CashBook\CashBookAlreadyExistsException;
use SolveBeam\Twinfield\CashBook\CashBookInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Twinfield cannot delete cash books, so this example always uses the same code.
$input = CashBookInput::create($argv[1] ?? 'EXAMPLE', 'Example cash')
    ->shortName('Example');

try {
    $cashBook = $twinfield->cashBooks()->create($input);
} catch (CashBookAlreadyExistsException $exception) {
    echo 'Already exists: ', $exception->reason, PHP_EOL;

    exit(0);
}

echo 'Created: ', $cashBook->code, ' ', $cashBook->name, ' (', $cashBook->shortName ?? '-', ')', PHP_EOL;
