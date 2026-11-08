# List offices

Shows all offices (companies, administrations) the logged in user has access to.

`offices()->list()` returns a list of `OfficeSummary` objects. It fetches all pages for you, so you get every office, also when there are hundreds. Each summary has:

- `code` and `name`, e.g. `1000` and `Example Holding B.V.`; pass the code (or the summary itself) to `$twinfield->company()`.
- `companyId`, the unique ID (GUID) of the company.
- `country`, e.g. `NL`.
- `vatPeriod` and `startingQuarter`, the VAT declaration period and starting quarter of the office.

## Code

```php
$twinfield = Example::twinfield();

foreach ($twinfield->offices()->list() as $office) {
    echo $office->code, ' ', $office->name, ' (', $office->country, ', ', $office->companyId, ')', PHP_EOL;
}
```

Looking for one office with all its settings? Use `$twinfield->offices()->get('1000')`, see [Read office](../read-office/).

## Run

```sh
php examples/offices/list-offices/example.php
```

```text
1000 Example Holding B.V. (NL, 0f8fad5b-d9cb-469f-a165-70867728950e)
1001 Example Retail B.V. (NL, 7c9e6679-7425-40de-944b-e07fc1f90ae7)
1002 Example Services B.V. (NL, f47ac10b-58cc-4372-a567-0e02b2c3d479)
```
