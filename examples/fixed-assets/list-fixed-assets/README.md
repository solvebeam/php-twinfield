# List fixed assets

Lists the fixed assets of the [new fixed assets module](/guides/fixed-assets/) with their status, book value and last depreciated period. Companies on the classic module use [List fixed assets (classic)](/examples/classic-fixed-assets/list-fixed-assets/).

- `fixedAssets()->search($pattern)` searches code and description, with filters such as `status()`, `regime()`, `inClass()`, `inGroup()` and `investedBetween()`.
- The book value and purchase value are those of the youngest regime; `get()` gives the values per regime.
- `postingErrorMessage` says why Twinfield could not post the last depreciation.

Needs the scope `readFixedAssets`.

## Code

```php
$twinfield = Example::twinfield();

$search = $twinfield->fixedAssets()->search($argv[1] ?? '*')->orderBy('code');

if (isset($argv[2])) {
    $search = $search->status(FixedAssetStatus::from($argv[2]));
}

foreach ($search->all() as $asset) {
    echo sprintf(
        '%s %s: %s, book value %s of %s%s',
        $asset->code,
        $asset->description,
        $asset->status->value,
        $asset->netBookValue,
        $asset->purchaseValue,
        $asset->lastDepreciatedPeriod === null ? '' : ', depreciated up to ' . $asset->lastDepreciatedPeriod,
    ), PHP_EOL;

    if ($asset->postingErrorMessage !== null) {
        echo '  Posting error: ', $asset->postingErrorMessage, PHP_EOL;
    }
}
```

## Run

```sh
php examples/fixed-assets/list-fixed-assets/example.php
php examples/fixed-assets/list-fixed-assets/example.php '6*' active
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
60000 Delivery van: active, book value 21250.00 of 32000.00, depreciated up to 2026/10
60001 Office furniture: active, book value 4100.00 of 8200.00, depreciated up to 2026/10
60002 Laptop: disposed, book value 0.00 of 1499.00, depreciated up to 2025/06
  Posting error: General ledger account 4800 needs a cost center.
```
