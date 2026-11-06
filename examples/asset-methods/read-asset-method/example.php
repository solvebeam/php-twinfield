<?php

declare(strict_types=1);

use SolveBeam\Twinfield\AssetMethod\AccountReference;
use SolveBeam\Twinfield\AssetMethod\AssetMethodNotFoundException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first asset method.
$code = $argv[1] ?? $twinfield->assetMethods()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no asset methods.' . PHP_EOL);

try {
    $method = $twinfield->assetMethods()->get($code);
} catch (AssetMethodNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$account = static fn (?AccountReference $account): string => $account === null
    ? '-'
    : $account->code . ' ' . $account->name;

echo 'Code:          ', $method->code, PHP_EOL;
echo 'Name:          ', $method->name, PHP_EOL;
echo 'Status:        ', $method->status->value, $method->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Calculation:   ', $method->calculationMethod->value, PHP_EOL;
echo 'Periods:       ', $method->periods, PHP_EOL;
echo 'Percentage:    ', $method->percentage ?? '-', PHP_EOL;
echo 'Depreciate:    ', $method->depreciationStart->value, PHP_EOL;
echo 'Purchase:      ', $account($method->balanceAccounts->purchaseValue), PHP_EOL;
echo 'Cumulative:    ', $account($method->balanceAccounts->depreciation), PHP_EOL;
echo 'Costs:         ', $account($method->profitLossAccounts->depreciation), PHP_EOL;
echo 'Disposal:      ', $account($method->profitLossAccounts->sales), PHP_EOL;

foreach ($method->freeTexts as $field) {
    if ($field->label !== null) {
        echo 'Free text ', $field->id, ':   ', $field->label, ' (', $field->type->value, ')', PHP_EOL;
    }
}
