# List changed customers

Lists the customers that changed in the last 7 days (or since a date), also deleted and archived ones, from the REST API. Useful to keep a copy of the customers in sync without reading all of them.

- `customers()->changes($since)` gives `DimensionChange` objects: GUID, name, status (`active`, `archived`, `deleted`) and when it changed. `suppliers()->changes()` does the same for suppliers.
- The GUID is the `uid` of the customer in the XML web services; read the details with `customers()->get($code)`.
- Twinfield also returns customers whose `modified` is older than the date, so check the date yourself if it matters.

Needs the scope `readCustomers`. Some companies answer this request with HTTP 500 (a Twinfield error): see [Documentation differences](/guides/documentation-differences/#rest-api).

## Code

```php
$twinfield = Example::twinfield();

$since = new DateTimeImmutable($argv[1] ?? '-7 days');
$changes = $twinfield->customers()->changes($since);
$total = $changes->limit(1)->get()->filteredTotal;

echo $total, ' customer(s) changed since ', $since->format('Y-m-d H:i'), PHP_EOL;

foreach ($changes->all() as $index => $change) {
    echo sprintf(
        '[%d/%d] %s %s: %s, %s',
        $index + 1,
        $total,
        $change->modified->format('Y-m-d H:i'),
        $change->name,
        $change->status->value,
        $change->id,
    ), PHP_EOL;
}
```

## Run

```sh
php examples/customers/list-changed-customers/example.php
php examples/customers/list-changed-customers/example.php '-1 day'
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
2 customer(s) changed since 2026-11-03 10:15
[1/2] 2026-11-05 14:02 Example Trading B.V.: active, 3f2504e0-4f89-11d3-9a0c-0305e82c3301
[2/2] 2026-11-08 09:30 Old Customer B.V.: archived, 6fa459ea-ee8a-3ca4-894e-db77e160355e
```
