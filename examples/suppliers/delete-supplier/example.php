<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Supplier\SupplierInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$suppliers = $twinfield->suppliers();

// The code from the command line, or else a new example supplier.
$example = !isset($argv[1]);
$code = $argv[1] ?? $suppliers->create(SupplierInput::create('Example supplier to delete'))->code;

$result = $suppliers->delete($code);

echo match ($result) {
    DeleteResult::Deleted => 'Deleted ' . $code,
    // A supplier used in a transaction cannot be deleted: Twinfield hides it.
    DeleteResult::Hidden => 'Hidden ' . $code . ': it is used in a transaction',
}, PHP_EOL;

echo 'Status: ', $suppliers->get($code)->status->value, PHP_EOL;

// Deleted and hidden suppliers can be made active again.
$suppliers->activate($code);

echo 'Status: ', $suppliers->get($code)->status->value, PHP_EOL;

// Clean up the example supplier.
if ($example) {
    $suppliers->delete($code);
}
