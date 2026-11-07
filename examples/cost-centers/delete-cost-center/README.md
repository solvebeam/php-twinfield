# Delete cost center

> **This example changes data in Twinfield.** It deletes the cost center again, but only run it on a test administration.

Creates a cost center (the code you pass, or the first unused code from `999` down), deletes it, makes it active again and deletes it once more.

A cost center that is used in a transaction cannot be deleted: Twinfield hides it instead. `delete()` tells you which one happened:

- `DeleteResult::Deleted`: the cost center is deleted (status `deleted`).
- `DeleteResult::Hidden`: the cost center is in use and now hidden (status `hide`).

`activate($code)` makes a deleted or hidden cost center active again. A deleted cost center keeps its code: `create()` with the same code throws a `DimensionAlreadyExistsException`.

## Code

```php
$twinfield = Example::twinfield();

$costCenters = $twinfield->costCenters();
$code = $argv[1] ?? Example::unusedCostCenterCode($twinfield);

$costCenters->create(CostCenterInput::create($code, 'Example cost center'));

$result = $costCenters->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;

echo 'Status now: ', $costCenters->get($code)->status->value, PHP_EOL;

// A deleted or hidden cost center can be made active again.
$costCenters->activate($code);

echo 'After activate(): ', $costCenters->get($code)->status->value, PHP_EOL;

// Clean up.
$costCenters->delete($code);
```

## Run

```sh
php examples/cost-centers/delete-cost-center/example.php
```

```text
Deleted 999.
Status now: deleted
After activate(): active
```
