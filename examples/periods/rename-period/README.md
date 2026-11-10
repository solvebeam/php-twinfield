# Rename period

Renames period 1 of the last year and then restores the old name.

> **This example changes data in Twinfield.** It restores the name afterwards, but only run it on a test administration.

Changes go through commands of the PeriodService, a [Command & Query web service](https://developers.twinfield.com/documentation/api/types/command-and-query-webservices). A command returns nothing; when Twinfield refuses it, you get a `CommandQueryFaultException` with an `errorCode` such as `PeriodNotFound` or `NameCanNotBeChanged` (see `PeriodServiceFaultCode`).

All commands of `periods()`:

| Method | What it does |
|---|---|
| `changeName($period, $name)` | Changes the name of a period, e.g. `changeName(Period::of(2025, 1), 'January')`. |
| `changeEndDate($period, $endDate)` | Changes the end date of a period. |
| `open($period)` / `close($period)` | Opens or closes a period for posting. |
| `createYear($numberOfPeriods)` | Creates a new year, e.g. with 12 periods. |
| `deleteYear($year)` | Deletes a year. |
| `resetYears($newYear, $newNumberOfPeriods)` | Deletes all years and starts again from `$newYear`. |

## Code

```php
$twinfield = Example::twinfield();

$periods = $twinfield->periods();
$years = $periods->years();

echo 'Company ', $twinfield->companyCode(), PHP_EOL;

if ($years === []) {
    echo 'The company has no years yet.', PHP_EOL;

    exit;
}

$period = Period::of(max($years), 1);

$original = $periods->get($period)->name;

if ($original === null) {
    echo 'Period ', $period, ' has no name to restore.', PHP_EOL;

    exit;
}

echo 'Before:   ', $original, PHP_EOL;

try {
    $periods->changeName($period, $original . ' (renamed)');
    echo 'Renamed:  ', $periods->get($period)->name, PHP_EOL;
} finally {
    $periods->changeName($period, $original);
    echo 'Restored: ', $periods->get($period)->name, PHP_EOL;
}
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/periods/rename-period/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
Company 1000
Before:   januari 2025
Renamed:  januari 2025 (renamed)
Restored: januari 2025
```
