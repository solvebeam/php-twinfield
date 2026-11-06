# Rename period

Renames period 1 of the last year and then restores the old name.

> **This example changes data in Twinfield.** It restores the name afterwards, but only run it on a test administration.

Changes go through commands of the PeriodService, a [Command & Query web service](https://developers.twinfield.com/documentation/api/types/command-and-query-webservices). A command returns nothing; when Twinfield refuses it, you get a `CommandQueryFaultException` with an `errorCode` such as `PeriodNotFound` or `NameCanNotBeChanged` (see `PeriodServiceFaultCode`).

All commands of `periods()`:

| Method | What it does |
|---|---|
| `changeName($year, $period, $name)` | Changes the name of a period. |
| `changeEndDate($year, $period, $endDate)` | Changes the end date of a period. |
| `open($year, $period)` / `close($year, $period)` | Opens or closes a period for posting. |
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

$year = max($years);

$nameOf = static function (int $number) use ($periods, $year): string {
    foreach ($periods->list($year) as $period) {
        if ($period->number === $number) {
            return $period->name;
        }
    }

    throw new RuntimeException("Period {$number} of {$year} not found.");
};

$original = $nameOf(1);
echo 'Before:   ', $original, PHP_EOL;

try {
    $periods->changeName($year, 1, $original . ' (renamed)');
    echo 'Renamed:  ', $nameOf(1), PHP_EOL;
} finally {
    $periods->changeName($year, 1, $original);
    echo 'Restored: ', $nameOf(1), PHP_EOL;
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
