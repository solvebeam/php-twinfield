# Read fixed asset

Shows one fixed asset: its status, asset method, depreciation settings, free texts, transaction lines and which settings Twinfield has locked.

`get()` throws a `DimensionNotFoundException` for an unknown code; `find()` returns null instead.

- `assetStatus` is where the asset is in its life: `ToBeActivated`, `Active`, `Inactive` or `Sold`. `status` is the status of the dimension (active, deleted or hidden), as for every dimension.
- Periods are `Period` objects and amounts are `Amount` objects (numeric strings, never floats). Both print as Twinfield writes them, e.g. `2026/04` and `2500.00`.
- `periodsInherited` is true when the number of periods comes from the asset method.
- The asset only has the values of the free texts; their labels are on the asset method, so the example reads that too.
- `transactionLines` are the purchase and later transactions with the asset as dimension 3. Twinfield links them when you book such a transaction.
- `lockedFields` lists what Twinfield does not allow to change now, e.g. the begin period of an active asset. `isLocked(ClassicFixedAssetField::DepreciateFrom)` checks one setting.

## Code

```php
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
```

## Run

```sh
php examples/classic-fixed-assets/read-fixed-asset/example.php 60010
```

```text
Code:              60010 Delivery van
Status:            Active (in use)
Asset method:      5YVEH 5 years linear - vehicles
Cost center:       00000 General
Purchase date:     2026-03-15
Depreciate from:   2026/04
Periods:           60
Residual value:    2500.00
Last depreciation: 2026/09
Sell date:         -
Groups:            VEHICLES
Serial number: WDB9066331S123456

Transaction lines:
  2026/03  INK 202600012/2  0300  42000.00

Locked: status, begin period, sell date, transaction lines, last depreciation
```
