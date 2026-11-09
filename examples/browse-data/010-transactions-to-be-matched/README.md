# Transactions still to be matched (010)

Browse code `010` (`BrowseCode::TransactionsToBeMatched`, "Nog af te letteren boekingen"): transaction lines on accounts with matching, such as suspense accounts, that are not matched yet.

- `equal('fin.trs.line.matchstatus', 'available')`: lines that can still be matched; Twinfield proposes this criterion. The values of the match status are `notmatchable`, `available`, `proposed` and `matched` (see [List browse fields](../list-browse-fields/)).
- `fin.trs.line.openbasevaluesigned`: the amount that is still open, in the base currency.
- `between('fin.trs.head.yearperiod', '2000/00', …)`: all years up to the last one. Status is not a criterion in 010.

Criteria: period, general ledger account, day book, number, match status and regime. See [`browse-definitions/010.xml`](../../../browse-definitions/010.xml) and the [browse data guide](https://php-twinfield.solvebeam.com/guides/browse-data/). For open items of customers and suppliers use [100](../100-customer-transactions/) and [200](../200-supplier-transactions/).

## Code

```php
$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));

$result = $twinfield->browse(BrowseCode::TransactionsToBeMatched)
    ->columns(
        'fin.trs.line.dim1',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.basevaluesigned',
        'fin.trs.line.openbasevaluesigned',
        'fin.trs.line.matchstatus',
    )
    ->between('fin.trs.head.yearperiod', '2000/00', sprintf('%d/55', $year))
    ->equal('fin.trs.line.matchstatus', 'available')
    ->get();

echo count($result), ' line(s) still to be matched up to ', $year, PHP_EOL;

foreach ($result as $index => $row) {
    echo sprintf(
        '[%d/%d] %s %s %d: %s, open %s (%s)',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim1'),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->amount('fin.trs.line.basevaluesigned'),
        $row->amount('fin.trs.line.openbasevaluesigned'),
        $row->displayName('fin.trs.line.matchstatus') ?? $row->string('fin.trs.line.matchstatus'),
    ), PHP_EOL;
}
```

## Run

```sh
php examples/browse-data/010-transactions-to-be-matched/example.php         # up to the last year
php examples/browse-data/010-transactions-to-be-matched/example.php 2024    # up to 2024
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
120 line(s) still to be matched up to 2025
[1/120] 17018 ACTIVA 202200008: -135983.00, open -135983.00 (Beschikbaar)
[2/120] 17018 ACTIVA 202200010: -135983.00, open -135983.00 (Beschikbaar)
[3/120] 17018 ACTIVA 202200011: 135983.00, open 135983.00 (Beschikbaar)
[4/120] 17018 ACTIVA 202200012: -122200.00, open -122200.00 (Beschikbaar)
…
[120/120] 16615 VRK_PIN 202200001: -17.36, open -17.36 (Beschikbaar)
```
