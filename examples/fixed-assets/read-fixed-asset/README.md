# Read fixed asset

Reads one fixed asset of the [new fixed assets module](/guides/fixed-assets/) with its depreciation per regime, its class and its history. Without an argument it reads the first asset.

- `fixedAssets()->get($id)` reads the asset by GUID, with all fields (`fields=*`).
- `depreciationSettings` has a `DepreciationSettings` per regime (fiscal, commercial, economic); the method can change over time, so each has a list of methods with the period they start.
- Classes, cost centers and locations are GUIDs with the year or period from which they apply. `getClass()` reads the class.
- `actions()` is the history: created, activated, depreciated, disposed, …, with the transactions each action posted.

Needs the scope `readFixedAssets`.

## Code

```php
$twinfield = Example::twinfield();
$fixedAssets = $twinfield->fixedAssets();

$id = $argv[1] ?? $fixedAssets->search()->limit(1)->get()->items[0]->id
    ?? throw new RuntimeException('The company has no fixed assets in the new fixed assets module.');

$asset = $fixedAssets->get($id);

echo $asset->code, ' ', $asset->description, ' (', $asset->status->value, ')', PHP_EOL;
echo 'Purchase value ', $asset->purchaseValue, ', book value ', $asset->netBookValue, ' ', $asset->currency, PHP_EOL;
echo 'Invested ', $asset->investmentDate->format('Y-m-d');
echo ', in use since ', $asset->firstUseDate->format('Y-m-d'), PHP_EOL;
echo 'Purchase value account ', $asset->purchaseValueAccount->code, ' ', $asset->purchaseValueAccount->name, PHP_EOL;

foreach ($asset->depreciationSettings as $settings) {
    foreach ($settings->methods as $method) {
        echo sprintf(
            'Depreciation %s from %s: %s, lifetime %s (%s), remainder value %s',
            $settings->regime->value,
            $method->from,
            $method->method->value,
            $method->lifetime,
            $method->lifetimeType->value,
            $settings->remainderValue,
        ), PHP_EOL;
    }
}

foreach ($asset->classes as $class) {
    echo 'Class from ', $class->year, ': ', $fixedAssets->getClass($class->id)->name, PHP_EOL;
}

echo PHP_EOL, 'History:', PHP_EOL;

foreach ($fixedAssets->actions($asset->id) as $action) {
    $transactions = array_map(
        static fn ($transaction): string => $transaction->dayBook . ' ' . $transaction->number,
        $action->transactions,
    );

    echo sprintf(
        '%s %s by %s%s',
        $action->timestamp->format('Y-m-d H:i'),
        $action->type->value,
        $action->user,
        $transactions === [] ? '' : ': ' . implode(', ', array_unique($transactions)),
    ), PHP_EOL;
}
```

## Run

```sh
php examples/fixed-assets/read-fixed-asset/example.php
php examples/fixed-assets/read-fixed-asset/example.php ffe2c8b3-e7f0-4793-a059-ffbfe3c154a9
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
60000 Delivery van (active)
Purchase value 32000.00, book value 21250.00 EUR
Invested 2025-03-15, in use since 2025-04-01
Purchase value account 0300 Vehicles
Depreciation fiscal from 2025/04: straightLine, lifetime 60 (linear), remainder value 2000.00
Depreciation commercial from 2025/04: straightLine, lifetime 48 (linear), remainder value 4000.00
Class from 2025: Vehicles

History:
2025-03-15 09:12 created by JDOE
2025-04-01 08:00 activated by JDOE
2025-04-30 23:10 depreciated by JDOE: ACTIVA 202500012
```
