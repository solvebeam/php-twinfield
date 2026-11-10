# Update fixed asset

> **This example changes data in Twinfield.** It creates a temporary fixed asset and deletes it again; only run it on a test administration.

Creates a fixed asset, changes its name, residual value, a free text and the number of periods, and deletes it again.

Start an update from `$asset->edit()`. It has the values of the asset that may be changed:

- Settings Twinfield has locked are left out, e.g. the begin period and last depreciation of an active asset. Setting one on that input throws a `LogicException` before anything is sent; check first with `$asset->isLocked(ClassicFixedAssetField::Periods)`.
- The number of periods is left out when it comes from the asset method, so it keeps following the method.
- The status, sell date and transaction lines cannot be set: Twinfield always locks the status and sell date, and links transaction lines when you book a transaction with the asset as dimension 3.

`update()` reads first and throws a `DimensionNotFoundException` when the asset does not exist.

## Code

```php
$twinfield = Example::twinfield();

$fixedAssets = $twinfield->classicFixedAssets();

$created = $fixedAssets->create(
    ClassicFixedAssetInput::create('Example delivery van')
        ->method(Example::assetMethodCode($twinfield))
        ->costCenter(Example::costCenterCode($twinfield)),
);

try {
    $asset = $fixedAssets->get($created->code);
    echo 'Before: ', $asset->name, ', residual value ', $asset->residualValue, PHP_EOL;

    // edit() leaves out what Twinfield has locked, e.g. the begin period of an active asset.
    $input = $asset->edit()
        ->name('Example delivery van (updated)')
        ->residualValue('1000.00')
        ->freeText(2, 'Lease contract 2026-001');

    if (!$asset->isLocked(ClassicFixedAssetField::Periods)) {
        $input = $input->periods(48);
    }

    $asset = $fixedAssets->update($input);
    echo 'After:  ', $asset->name, ', residual value ', $asset->residualValue, PHP_EOL;
    echo 'Periods: ', $asset->periods ?? '-', PHP_EOL;
} finally {
    $fixedAssets->delete($created->code);
}
```

## Run

```sh
php examples/classic-fixed-assets/update-fixed-asset/example.php
```

```text
Before: Example delivery van, residual value 0.00
After:  Example delivery van (updated), residual value 1000.00
Periods: 48
```
