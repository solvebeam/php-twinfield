# List VAT code rates

Lists the VAT codes with the rate on a date and the accounts the VAT goes to, from the REST API (without argument: today).

- `vatCodes()->searchDetails()` reads the VAT codes with their GUID, their rates over time and the allocation rules of each rate.
- `rateOn($date)` picks the rate with the latest start date on or before the date.
- Allocation rules refer to accounts by GUID; `ledgerAccounts()->get()` gives the code.

`vatCodes()->get($code)` reads the same VAT code with the XML web services, with account codes but without GUIDs. Needs the scopes `readVat` and `readBookkeeping`.

## Code

```php
$twinfield = Example::twinfield();

$date = new DateTimeImmutable($argv[1] ?? 'today');
$accounts = [];

foreach ($twinfield->vatCodes()->searchDetails()->orderByCode()->all() as $vatCode) {
    $rate = $vatCode->rateOn($date);

    echo $vatCode->code, ' ', $vatCode->name, ' (', $vatCode->type->value, '): ';
    echo $rate === null
        ? 'no rate on ' . $date->format('Y-m-d')
        : $rate->percentage . '% since ' . $rate->startDate->format('Y-m-d');
    echo PHP_EOL;

    foreach ($rate === null ? [] : $rate->allocationRules as $rule) {
        if ($rule->generalLedgerAccountId === null) {
            continue;
        }

        $id = $rule->generalLedgerAccountId;
        $accounts[$id] ??= $twinfield->ledgerAccounts()->get($id)->code;

        echo '  ', $rule->percentage, '% of the VAT on ', $accounts[$id];
        echo $rule->isVatLine ? ' (VAT line)' : '', PHP_EOL;
    }
}
```

## Run

```sh
php examples/vat-codes/list-vat-code-rates/example.php
php examples/vat-codes/list-vat-code-rates/example.php 2018-06-01
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
IH VAT purchases high (purchase): 21% since 2012-10-01
  100% of the VAT on 1520 (VAT line)
ICV-H EU acquisitions high (purchase): 21% since 2012-10-01
  -100% of the VAT on 1510 (VAT line)
  100% of the VAT on 1520 (VAT line)
VH VAT sales high (sales): 21% since 2012-10-01
  100% of the VAT on 1510 (VAT line)
```
