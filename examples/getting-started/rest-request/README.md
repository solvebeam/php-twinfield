# REST API request

Calls the [Twinfield REST API](/guides/rest-api/) directly with `$twinfield->rest()`, for an endpoint the SDK has no method for yet.

- The REST API identifies the company by GUID, not by code. `$twinfield->companyId()` gives it: the SDK looks it up once with the Finder (`OFF` with `includeid`), or takes it from `company('1000', $guid)` or an `OfficeSummary`.
- Copy the path from the [Swagger definition](https://api.accounting.twinfield.com/Api/swagger/ui/index): `{organisationId}` and `{companyId}` are filled in.
- `json()` returns a `JsonValue` with typed readers (`string()`, `int()`, `decimal()`, `list()`, …) that throw an `UnexpectedResponseException` when a value is missing or invalid. Numbers are read as exact strings, never as floats.
- Without `fields=*` many endpoints return only their default fields.

Every REST operation needs its own scope, here `readFixedAssets`; without it Twinfield answers HTTP 403. See [Scopes](/guides/rest-api/#scopes).

## Code

```php
$twinfield = Example::twinfield();

echo 'Company ', $twinfield->companyCode(), ' has GUID ', $twinfield->companyId(), PHP_EOL;

// A path from the Swagger definition: the SDK fills in the GUIDs of the organisation and company.
$response = $twinfield->rest(
    'GET',
    '/organisations/{organisationId}/companies/{companyId}/fixedassets/classes',
    ['fields' => '*', 'limit' => 5],
);

$json = $response->json();

echo $json->int('filteredTotal'), ' fixed asset classes', PHP_EOL;

foreach ($json->list('items') as $class) {
    echo $class->string('name'), ': ', $class->string('method'), ', lifetime ', $class->decimal('lifetime'), PHP_EOL;
}
```

## Run

```sh
php examples/getting-started/rest-request/example.php
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
Company 1000 has GUID ffe2c8b3-e7f0-4793-a059-ffbfe3c154a9
3 fixed asset classes
Buildings: straightLine, lifetime 360
Machines: straightLine, lifetime 60
Vehicles: straightLine, lifetime 48
```
