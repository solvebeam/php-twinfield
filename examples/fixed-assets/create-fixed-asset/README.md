# Create fixed asset

> **This example changes data in Twinfield.** It deletes the fixed asset again, but only run it on a test administration.

Creates a fixed asset with the first active asset method and the first cost center of the company, and deletes it again.

Without `code()`, Twinfield gives the asset the next free code of the `AST` dimension type. With a code, `create()` reads first and throws a `DimensionAlreadyExistsException` when it is taken; `save()` creates or updates in one request.

`FixedAssetInput` checks the input before anything is sent: codes are 1–16 characters `A-Z`, `0-9`, `_`, `-` or `+`, amounts are numeric strings with at most two decimals, percentages use a dot (`'12.5'`), and free texts are fields 1 to 5. Only what you set is sent; the rest gets the default of the asset method.

A new asset has the status `ToBeActivated`. Book its purchase with the asset code as dimension 3, and Twinfield links that transaction line to the asset.

## Code

```php
$twinfield = Example::twinfield();

$fixedAssets = $twinfield->fixedAssets();

$input = FixedAssetInput::create('Example delivery van')
    ->method(Example::assetMethodCode($twinfield))
    ->costCenter(Example::costCenterCode($twinfield))
    ->purchaseDate(new DateTimeImmutable('2026-03-15'))
    ->depreciateFrom(Period::of(2026, 4))
    ->residualValue('2500.00')
    ->freeText(1, 'WDB9066331S123456');

try {
    // Without code(), Twinfield gives the asset the next free code.
    $asset = $fixedAssets->create($input);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Created ', $asset->code, ' ', $asset->name, PHP_EOL;
echo 'Status:  ', $asset->assetStatus->name, PHP_EOL;
echo 'Method:  ', $asset->method?->code, ', ', $asset->periods ?? '-', ' periods', PHP_EOL;
echo 'From:    ', $asset->depreciateFrom, PHP_EOL;

// Clean up: delete the example asset again.
$fixedAssets->delete($asset->code);

echo 'Deleted ', $asset->code, PHP_EOL;
```

## Run

```sh
php examples/fixed-assets/create-fixed-asset/example.php
```

```text
Created 60011 Example delivery van
Status:  ToBeActivated
Method:  5YVEH, 60 periods
From:    2026/04
Deleted 60011
```
