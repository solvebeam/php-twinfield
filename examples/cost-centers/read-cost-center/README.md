# Read cost center

Reads one cost center: the code from the command line, or else the first cost center.

`get()` returns a `CostCenter`, or throws a `DimensionNotFoundException` when the code does not exist. Use `find()` to get `null` instead. A code that does not match the code format of the company cannot exist, so it is also "not found".

A cost center has a name of at most 80 characters and these read-only fields:

- `status`: `DimensionStatus::Active`, `Deleted` or `Hidden` (deleted while in use).
- `inUse`: whether the cost center is used in a transaction.
- `behaviour`: `normal`, `system` or `template`.
- `touched`: how often the cost center was changed, and `modified`: the last change.

The other dimension fields (short name, periods, website, …) are not in use for cost centers, so the SDK leaves them out.

## Code

```php
$twinfield = Example::twinfield();

// The code from the command line, or else the first cost center.
$code = $argv[1] ?? $twinfield->costCenters()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no cost centers.' . PHP_EOL);

try {
    $costCenter = $twinfield->costCenters()->get($code);
} catch (DimensionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo 'Code:       ', $costCenter->code, PHP_EOL;
echo 'Name:       ', $costCenter->name, PHP_EOL;
echo 'UID:        ', $costCenter->uid, PHP_EOL;
echo 'Status:     ', $costCenter->status->value, $costCenter->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Behaviour:  ', $costCenter->behaviour->value, PHP_EOL;
echo 'Modified:   ', $costCenter->modified?->format('Y-m-d H:i') ?? '-', PHP_EOL;
echo 'Changes:    ', $costCenter->touched, PHP_EOL;
```

## Run

```sh
php examples/cost-centers/read-cost-center/example.php 100
```

```text
Code:       100
Name:       Sales
UID:        5f3c2a1e-7b4d-4e8f-9a6b-1c2d3e4f5a6b
Status:     active (in use)
Behaviour:  normal
Modified:   2025-03-14 09:30
Changes:    2
```
