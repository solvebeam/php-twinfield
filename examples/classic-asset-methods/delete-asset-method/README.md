# Delete asset method

> **This example changes data in Twinfield.** Only run it on a test administration.

Creates an asset method and deletes it again.

`delete()` returns a `DeleteResult`: `Deleted`, or `Hidden` when fixed assets use the asset method, because Twinfield hides it instead. `activate()` makes a deleted or hidden asset method active again. Both throw an `AssetMethodNotFoundException` when the code does not exist.

## Code

```php
$twinfield = Example::twinfield();

$assetMethods = $twinfield->classicAssetMethods();

$result = $assetMethods->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;
```

## Run

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/classic-asset-methods/delete-asset-method/example.php
```

```text
Deleted EXAMPLE999.
Status now: deleted
```
