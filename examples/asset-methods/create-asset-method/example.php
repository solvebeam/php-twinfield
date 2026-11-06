<?php

declare(strict_types=1);

use SolveBeam\Twinfield\AssetMethod\AssetMethodInput;
use SolveBeam\Twinfield\AssetMethod\DepreciationStart;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$assetMethods = $twinfield->assetMethods();
$code = $argv[1] ?? Example::unusedAssetMethodCode($twinfield);

// Use the accounts of an existing asset method, so they exist in the company.
$template = $assetMethods->get(
    $assetMethods->search()->limit(1)->get()->items[0]->code ?? exit('The company has no asset methods.' . PHP_EOL),
);

$method = $assetMethods->create(
    AssetMethodInput::create($code, 'Example laptops')
        ->linear(36)
        ->depreciateFrom(DepreciationStart::FromPurchaseDate)
        ->purchaseValueAccount($template->balanceAccounts->purchaseValue?->code)
        ->cumulativeDepreciationAccount($template->balanceAccounts->depreciation?->code)
        ->depreciationCostsAccount($template->profitLossAccounts->depreciation?->code)
        ->freeText(1, 'Serial number'),
);

echo 'Created ', $method->code, ' ', $method->name, ': ';
echo $method->periods, ' periods ', $method->calculationMethod->value, PHP_EOL;

// Clean up: delete the example asset method again.
$assetMethods->delete($method->code);

echo 'Deleted ', $method->code, PHP_EOL;
