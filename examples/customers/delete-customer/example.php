<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Customer\CustomerInput;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$customers = $twinfield->customers();

// The code from the command line, or else a new example customer.
$example = !isset($argv[1]);
$code = $argv[1] ?? $customers->create(CustomerInput::create('Example customer to delete'))->code;

$result = $customers->delete($code);

echo match ($result) {
    DeleteResult::Deleted => 'Deleted ' . $code,
    // A customer used in a transaction cannot be deleted: Twinfield hides it.
    DeleteResult::Hidden => 'Hidden ' . $code . ': it is used in a transaction',
}, PHP_EOL;

echo 'Status: ', $customers->get($code)->status->value, PHP_EOL;

// Deleted and hidden customers can be made active again.
$customers->activate($code);

echo 'Status: ', $customers->get($code)->status->value, PHP_EOL;

// Clean up the example customer.
if ($example) {
    $customers->delete($code);
}
