# Update cost center

> **This example changes data in Twinfield.** It creates a cost center, renames it and deletes it again. Only run it on a test administration.

`$costCenter->edit()` gives a `CostCenterInput` with the current values. Change what you need and pass it to `update()`, which checks that the cost center exists and returns the saved `CostCenter`.

Only the name can be changed. The code is the key of the cost center: to use another code, create a new cost center.

## Code

```php
$twinfield = Example::twinfield();

$costCenters = $twinfield->costCenters();
$code = $argv[1] ?? Example::unusedCostCenterCode($twinfield);

$costCenters->create(CostCenterInput::create($code, 'Example cost center'));

try {
    $costCenter = $costCenters->get($code);
    echo 'Before: ', $costCenter->name, PHP_EOL;

    $costCenter = $costCenters->update($costCenter->edit()->name('Example cost center (updated)'));
    echo 'After:  ', $costCenter->name, PHP_EOL;
} finally {
    $costCenters->delete($code);
}
```

## Run

```sh
php examples/cost-centers/update-cost-center/example.php
```

```text
Before: Example cost center
After:  Example cost center (updated)
```
