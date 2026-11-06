# List periods

Shows the years of the company and the periods of the last year.

Years and periods come from the PeriodService, a [Command & Query web service](https://developers.twinfield.com/documentation/api/types/command-and-query-webservices). These services always work on one company, so the client needs a company (`company('1000')`); `Example::twinfield()` uses your default company.

- `periods()->years()`: all years, e.g. `[2024, 2025]`.
- `periods()->list(2025)`: all periods of a year as `Period` objects with `number`, `name`, `endDate` and `open`.

Besides the regular periods (1–12), a year has special periods such as 0 (opening balance) and 55 (preceding journal entries). Those have no end date: `endDate` is `null`. Period names are in the language of the company.

## Code

```php
$twinfield = Example::twinfield();

$years = $twinfield->periods()->years();

echo 'Company ', $twinfield->companyCode(), PHP_EOL;

if ($years === []) {
    echo 'The company has no years yet.', PHP_EOL;

    exit;
}

echo count($years), ' years, from ', min($years), ' to ', max($years), PHP_EOL;

foreach ($twinfield->periods()->list(max($years)) as $period) {
    echo sprintf(
        '%2d  %-35s %-10s  %s',
        $period->number,
        $period->name,
        $period->endDate?->format('Y-m-d') ?? '-',
        $period->open ? 'open' : 'closed',
    ), PHP_EOL;
}
```

A year that does not exist gives a `CommandQueryFaultException` with `errorCode` `YearNotFound` (see `PeriodServiceFaultCode`).

## Run

```sh
php examples/periods/list-periods/example.php
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
Company 1000
3 years, from 2023 to 2025
 0  Openingsbalans 2025                 -           open
 1  januari 2025                        2025-01-31  open
 2  februari 2025                       2025-02-28  open
…
12  december 2025                       2025-12-31  open
55  Voorafgaande journaalposten 2025    -           open
```
