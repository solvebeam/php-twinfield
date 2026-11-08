# Read project

Reads all settings of one project: the code from the command line, or else the first project.

`get()` returns a `Project`, or throws a `DimensionNotFoundException` when the code does not exist. Use `find()` to get `null` instead.

`$project->vatCode` is the VAT code for all activities of the project; an activity with its own VAT code uses that one instead.

The time & expenses settings are in `$project->projects`, the same `ProjectSettings` as an activity has:

- `validFrom` / `validTill`: the dates users can book hours on the project (`null`: no limit).
- `invoiceDescription`: longer description for the invoice template.
- `customer`, `rate` and `authoriser`: a `CodeSetting` with a `code` and a `ChangeMode`.
- `billable`: a `BillableSetting` with `billable`, `forRatio` (counts for the productivity ratio) and a `ChangeMode`.
- `quantities`: extra quantities such as kilometres.

`ChangeMode` says whether users may change the setting in their timesheet (`Allow`, `Disallow`), or whether it comes from the activity or user level (`Inherit`). Inherited settings have no value of their own, so `code` and `billable` are `null` then.

## Code

```php
$twinfield = Example::twinfield();

// The code from the command line, or else the first project.
$code = $argv[1] ?? $twinfield->projects()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no projects.' . PHP_EOL);

try {
    $project = $twinfield->projects()->get($code);
} catch (DimensionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$settings = $project->projects;

// A code with its change mode, e.g. "1010 (Disallow)" or "- (Inherit)".
$setting = static fn (CodeSetting $setting): string => ($setting->code ?? '-') . ' (' . $setting->change->name . ')';

echo 'Code:        ', $project->code, PHP_EOL;
echo 'Name:        ', $project->name, PHP_EOL;
echo 'Short name:  ', $project->shortName ?? '-', PHP_EOL;
echo 'Status:      ', $project->status->value, $project->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Modified:    ', $project->modified?->format('Y-m-d H:i') ?? '-', PHP_EOL;
echo 'VAT code:    ', $project->vatCode ?? '-', PHP_EOL;
echo 'Valid from:  ', $settings->validFrom?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Valid till:  ', $settings->validTill?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Invoice:     ', $settings->invoiceDescription ?? '-', PHP_EOL;
echo 'Customer:    ', $setting($settings->customer), PHP_EOL;
echo 'Authoriser:  ', $setting($settings->authoriser), PHP_EOL;
echo 'Rate:        ', $setting($settings->rate), PHP_EOL;
echo 'Billable:    ', match ($settings->billable->billable) {
    true => 'yes',
    false => 'no',
    null => '-',
}, ' (', $settings->billable->change->name, ')', $settings->billable->forRatio ? ', counts for the ratio' : '', PHP_EOL;

foreach ($settings->quantities as $quantity) {
    echo 'Quantity:    ', $quantity->label, $quantity->mandatory ? ' (mandatory)' : '', PHP_EOL;
}
```

## Run

```sh
php examples/projects/read-project/example.php P0001
```

```text
Code:        P0001
Name:        Website redesign
Short name:  Website
Status:      active (in use)
Modified:    2025-03-14 09:30
VAT code:    VH
Valid from:  2025-01-01
Valid till:  2025-12-31
Invoice:     Redesign of the website & the API documentation
Customer:    1010 (Allow)
Authoriser:  SUPER (Disallow)
Rate:        T001 (Disallow)
Billable:    yes (Allow), counts for the ratio
Quantity:    KM
```
