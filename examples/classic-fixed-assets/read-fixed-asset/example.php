<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ClassicFixedAsset\ClassicFixedAssetField;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first fixed asset.
$code = $argv[1] ?? $twinfield->classicFixedAssets()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no fixed assets.' . PHP_EOL);

try {
    $asset = $twinfield->classicFixedAssets()->get($code);
} catch (DimensionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo 'Code:              ', $asset->code, ' ', $asset->name, PHP_EOL;
echo 'Status:            ', $asset->assetStatus->name, $asset->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Asset method:      ', $asset->method === null ? '-' : $asset->method->code . ' ' . $asset->method->name, PHP_EOL;
echo 'Cost center:       ', $asset->costCenter->code, ' ', $asset->costCenter->name, PHP_EOL;
echo 'Purchase date:     ', $asset->purchaseDate?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Depreciate from:   ', $asset->depreciateFrom ?? '-', PHP_EOL;
echo 'Periods:           ', $asset->periods ?? '-', $asset->periodsInherited ? ' (from the asset method)' : '', PHP_EOL;
echo 'Residual value:    ', $asset->residualValue, PHP_EOL;
echo 'Last depreciation: ', $asset->lastDepreciation ?? '-', PHP_EOL;
echo 'Sell date:         ', $asset->sellDate?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Groups:            ', implode(', ', $asset->groups) ?: '-', PHP_EOL;

// The asset method has the labels of the free text fields.
if ($asset->method !== null) {
    $method = $twinfield->classicAssetMethods()->get($asset->method->code);

    foreach ($method->freeTexts as $field) {
        if ($field->label !== null) {
            echo $field->label, ': ', $asset->freeText($field->id) ?? '-', PHP_EOL;
        }
    }
}

echo PHP_EOL, 'Transaction lines:', PHP_EOL;

foreach ($asset->transactionLines as $line) {
    echo '  ', $line->period, '  ', $line->transactionType, ' ', $line->number, '/', $line->line,
        '  ', $line->account()?->code, '  ', $line->amount, PHP_EOL;
}

if ($asset->transactionLines === []) {
    echo '  none yet', PHP_EOL;
}

echo PHP_EOL, 'Locked: ', implode(', ', array_map(
    static fn (ClassicFixedAssetField $field): string => $field->label(),
    $asset->lockedFields,
)), PHP_EOL;
