# List posting rules

Lists the posting rules of the customers and suppliers changed since a date (without argument: all), from the REST API. A posting rule says how an invoice of the relation is divided over accounts.

- `postingRules()->modifiedSince($since)` needs a date; use one long ago for all rules.
- `accountId` is the GUID of the customer or supplier (the `uid` of the dimension); the accounts of the lines are GUIDs as well.
- A line can have no general ledger account, only a VAT code.

Needs the scopes `readCustomers` and `readSuppliers`.

## Code

```php
$twinfield = Example::twinfield();

$since = new DateTimeImmutable($argv[1] ?? '2000-01-01');

foreach ($twinfield->postingRules()->modifiedSince($since)->all() as $rule) {
    echo sprintf(
        '%s rule of %s: %s%s, %d line(s)',
        $rule->type->value,
        $rule->accountId,
        $rule->description ?? '(no description)',
        $rule->amount === null ? '' : ' for ' . $rule->amount,
        count($rule->lines),
    ), PHP_EOL;

    foreach ($rule->lines as $line) {
        echo '  account ', $line->generalLedgerAccountId ?? '(none)';
        echo $line->ratio === null ? '' : ', ratio ' . $line->ratio, PHP_EOL;
    }
}
```

## Run

```sh
php examples/posting-rules/list-posting-rules/example.php
php examples/posting-rules/list-posting-rules/example.php 2026-01-01
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
sales rule of 3f2504e0-4f89-11d3-9a0c-0305e82c3301: Rent and service costs for 1250.00, 2 line(s)
  account 6fa459ea-ee8a-3ca4-894e-db77e160355e, ratio 80
  account 16fd2706-8baf-433b-82eb-8c7fada847da, ratio 20
```
