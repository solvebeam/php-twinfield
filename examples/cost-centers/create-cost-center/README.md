# Create cost center

> **This example changes data in Twinfield.** It deletes the cost center again, but only run it on a test administration.

Creates a cost center with the code you pass, or the first unused code from `999` down, and deletes it again.

`CostCenterInput::create($code, $name)` checks the input before anything is sent: the code must be 1–16 characters `A-Z`, `0-9`, `_`, `-` or `+`, and the name must be 1–80 characters. Each company also has its own code format for cost centers (in Twinfield under dimension types, `KPL`); a code that does not match is refused with a `ProcessXmlException`.

`create()` reads first, so it never overwrites an existing cost center: it throws a `DimensionAlreadyExistsException` instead. Use `save()` to create or update in one request.

## Code

```php
$twinfield = Example::twinfield();

$costCenters = $twinfield->costCenters();
$code = $argv[1] ?? Example::unusedCostCenterCode($twinfield);

try {
    $costCenter = $costCenters->create(CostCenterInput::create($code, 'Example cost center'));
} catch (DimensionAlreadyExistsException $exception) {
    echo 'Cost center ', $exception->dimensionCode, ' already exists: use update().', PHP_EOL;

    exit(1);
}

echo 'Created ', $costCenter->code, ' ', $costCenter->name, ' (', $costCenter->uid, ')', PHP_EOL;

// Clean up: delete the example cost center again.
$costCenters->delete($costCenter->code);

echo 'Deleted ', $costCenter->code, PHP_EOL;
```

## Run

```sh
php examples/cost-centers/create-cost-center/example.php
```

```text
Created 999 Example cost center (9b2e4c6d-1a3f-4d5e-8b7c-0f1e2d3c4b5a)
Deleted 999
```
