<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BankBook\BankAccountInput;
use SolveBeam\Twinfield\BankBook\BankBookAlreadyExistsException;
use SolveBeam\Twinfield\BankBook\BankBookInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Twinfield cannot delete bank books, so this example always uses the same code.
$input = BankBookInput::create($argv[1] ?? 'EXAMPLE', 'Example bank')
    ->shortName('Example')
    ->bankAccount(
        BankAccountInput::create()
            ->name('Example B.V.')
            ->iban('NL91 ABNA 0417 1643 00')
            ->bic('ABNANL2A')
            ->country('NL'),
    );

try {
    $bankBook = $twinfield->bankBooks()->create($input);
} catch (BankBookAlreadyExistsException $exception) {
    echo 'Already exists: ', $exception->reason, PHP_EOL;

    exit(0);
}

echo 'Created: ', $bankBook->code, ' ', $bankBook->name, ' (', $bankBook->bankAccount->iban, ')', PHP_EOL;
