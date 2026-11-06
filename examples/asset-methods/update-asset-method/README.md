# Update asset method

> **This example changes data in Twinfield.** It deletes the asset method again, but only run it on a test administration.

Creates an asset method, changes its name and calculation method, and deletes it again.

`update()` sends all values of the input, so values you leave out are cleared. Start from `$method->edit()`: that gives an `AssetMethodInput` with all current values, and you only change what you need. The calculation settings are the exception: they are only sent when set, and `edit()` always sets them.

`update()` checks first that the asset method exists and throws an `AssetMethodNotFoundException` when it does not. Afterwards it reads the asset method back, so you get the saved values.

## Code

```php
$twinfield = Example::twinfield();

$assetMethods = $twinfield->assetMethods();
$code = $argv[1] ?? Example::unusedAssetMethodCode($twinfield);

$method = $assetMethods->create(AssetMethodInput::create($code, 'Example laptops')->linear(36));

try {
    $method = $assetMethods->update(
        $method->edit()
            ->name('Example laptops (updated)')
            ->reducingBalance('25'),
    );

    echo 'After:  ', $method->name, ', ', $method->calculationMethod->value, ' ', $method->percentage, '%', PHP_EOL;
} finally {
    $assetMethods->delete($code);
}
```

## Run

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/asset-methods/update-asset-method/example.php
```

```text
Before: Example laptops, linear 36 periods
After:  Example laptops (updated), reducebalance 25%
```
