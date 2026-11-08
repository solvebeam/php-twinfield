# Delete fixed asset

> **This example changes data in Twinfield.** Only run it on a test administration.

Deletes a fixed asset and makes it active again. Without a code, it creates an example asset first and deletes it at the end; an asset you pass stays active.

`delete()` returns a `DeleteResult`: `Deleted`, or `Hidden` when the asset is used in a transaction, because Twinfield then hides it instead. `activate()` makes a deleted or hidden asset active again.

## Code

```php
$twinfield = Example::twinfield();

$fixedAssets = $twinfield->fixedAssets();

// The code from the command line, or else a new example asset.
$code = $argv[1] ?? null;
$example = $code === null;

$code ??= $fixedAssets->create(
    FixedAssetInput::create('Example delivery van')
        ->method(Example::assetMethodCode($twinfield))
        ->costCenter(Example::costCenterCode($twinfield)),
)->code;

$result = $fixedAssets->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is used in a transaction, so Twinfield hid it instead.",
}, PHP_EOL;

echo 'Status now: ', $fixedAssets->get($code)->status->value, PHP_EOL;

// A deleted or hidden asset can be made active again.
$fixedAssets->activate($code);

echo 'After activate(): ', $fixedAssets->get($code)->status->value, PHP_EOL;

// Clean up the example asset; an asset you passed stays active.
if ($example) {
    $fixedAssets->delete($code);
}
```

## Run

```sh
php examples/fixed-assets/delete-fixed-asset/example.php
```

```text
Deleted 60011.
Status now: deleted
After activate(): active
```
