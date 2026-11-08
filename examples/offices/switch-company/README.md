# Switch company

Runs the same call for several companies (offices).

Most Twinfield calls work on one company. `company('1000')` gives you a client for that company; it also takes an office from `offices()->list()` or `offices()->get()`. The client is immutable: `company()` returns a **new** client and leaves the original as it was, so you can safely keep one client per company.

## Code

```php
$twinfield = Example::twinfield();

foreach (array_slice($twinfield->offices()->list(), 0, 3) as $office) {
    $company = $twinfield->company($office);

    $dimensions = $company->finder(FinderType::Dimensions)->limit(1)->get()->totalRows;

    echo "{$office->code} {$office->name}: {$dimensions} dimensions", PHP_EOL;
}

echo 'The default company is still ', $twinfield->companyCode(), PHP_EOL;
```

`limit(1)` keeps the call small: only the total (`totalRows`) is needed here.

## Run

```sh
php examples/offices/switch-company/example.php
```

```text
1000 Example Holding B.V.: 412 dimensions
1001 Example Retail B.V.: 1290 dimensions
1002 Example Services B.V.: 87 dimensions
The default company is still 1000
```
