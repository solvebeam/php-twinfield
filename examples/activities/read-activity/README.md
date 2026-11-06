# Read activity

Reads all settings of one activity: the code from the command line, or else the first activity.

`get()` returns an `Activity`, or throws a `DimensionNotFoundException` when the code does not exist. Use `find()` to get `null` instead.

The time & expenses settings are in `$activity->projects`:

- `validFrom` / `validTill`: the dates users can book hours on the activity (`null`: no limit).
- `customer`, `rate` and `authoriser`: a `CodeSetting` with a `code` and a `ChangeMode`.
- `billable`: a `BillableSetting` with `billable`, `forRatio` (counts for the productivity ratio) and a `ChangeMode`.
- `quantities`: extra quantities such as kilometres.

`ChangeMode` says whether users may change the setting in their timesheet (`Allow`, `Disallow`), or whether it comes from the project or user level (`Inherit`). Inherited settings have no value of their own, so `code` and `billable` are `null` then.

## Code

```php
$twinfield = Example::twinfield();

// The code from the command line, or else the first activity.
$code = $argv[1] ?? $twinfield->activities()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no activities.' . PHP_EOL);

try {
    $activity = $twinfield->activities()->get($code);
} catch (DimensionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$projects = $activity->projects;

echo 'Code:        ', $activity->code, PHP_EOL;
echo 'Name:        ', $activity->name, PHP_EOL;
echo 'Short name:  ', $activity->shortName ?? '-', PHP_EOL;
echo 'Status:      ', $activity->status->value, $activity->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Modified:    ', $activity->modified?->format('Y-m-d H:i') ?? '-', PHP_EOL;
echo 'VAT code:    ', $activity->vatCode ?? '-', PHP_EOL;
echo 'Valid from:  ', $projects->validFrom?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Valid till:  ', $projects->validTill?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Customer:    ', $projects->customer->code ?? '-', ' (', $projects->customer->change->name, ')', PHP_EOL;
echo 'Rate:        ', $projects->rate->code ?? '-', ' (', $projects->rate->change->name, ')', PHP_EOL;
echo 'Billable:    ', match ($projects->billable->billable) {
    true => 'yes',
    false => 'no',
    null => '-',
}, ' (', $projects->billable->change->name, ')', PHP_EOL;

foreach ($projects->quantities as $quantity) {
    echo 'Quantity:    ', $quantity->label, $quantity->mandatory ? ' (mandatory)' : '', PHP_EOL;
}
```

## Run

```sh
php examples/activities/read-activity/example.php A001
```

```text
Code:        A001
Name:        Financial administration
Short name:  Fin
Status:      active
Modified:    2013-01-28 19:07
VAT code:    -
Valid from:  -
Valid till:  -
Customer:    - (Inherit)
Rate:        - (Inherit)
Billable:    - (Inherit)
```
