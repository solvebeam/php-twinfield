# Validate balances

Step 3 of a shadow database: checks that the [initial](../initial-load/) and [incremental load](../incremental-load/) are complete, by comparing the balance per general ledger account in the shadow database with the balance Twinfield calculates.

- `browse(BrowseCode::AnnualReportTotals)`: browse code 040_1, the annual report totals. Twinfield groups and sums the lines by the columns you ask for: with `fin.trs.line.dim1` and `fin.trs.line.basevaluesigned` you get the balance per account in the base currency.
- `between('fin.trs.head.yearperiod', …)`: one year at a time, as Twinfield advises.
- `equal('fin.trs.head.status', 'normal')` and `equal('fin.trs.head.balanceregimeprompt', Regime::Generic)`: the same transactions as in the shadow database. In 040_1 the regime field is `fin.trs.head.balanceregimeprompt`, not `fin.trs.head.regime`.
- Rows of 040_1 are totals, so they have no `transactionLine` (it is null).
- The balances are compared with `Amount`, so there are no rounding differences: SQL would sum the amounts as floats.

When an account differs, run the incremental load, or the initial load of that year again.

## Code

```php
$twinfield = Example::twinfield();

$database = ShadowDatabase::open($twinfield);
$year = (int) ($argv[1] ?? Example::lastYear($twinfield));

// The balance per general ledger account, summed by Twinfield.
$result = $twinfield->browse(BrowseCode::AnnualReportTotals)
    ->columns('fin.trs.line.dim1', 'fin.trs.line.basevaluesigned')
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal')
    ->equal('fin.trs.head.balanceregimeprompt', Regime::Generic)
    ->get();

$twinfieldBalances = [];

foreach ($result as $row) {
    $account = $row->string('fin.trs.line.dim1');
    $balance = $row->amount('fin.trs.line.basevaluesigned');
    $twinfieldBalances[$account] = ($twinfieldBalances[$account] ?? Amount::zero())->plus($balance);
}

$shadowBalances = $database->balances($year);
$accounts = array_unique([...array_keys($twinfieldBalances), ...array_keys($shadowBalances)]);
sort($accounts, SORT_STRING);

echo 'Balances of company ', $twinfield->companyCode(), ' in ', $year, ': ',
    count($accounts), ' general ledger account(s)', PHP_EOL;

$differences = 0;

foreach ($accounts as $index => $account) {
    $expected = $twinfieldBalances[$account] ?? Amount::zero();
    $actual = $shadowBalances[$account] ?? Amount::zero();
    $same = $expected->equals($actual);

    if (!$same) {
        $differences++;
    }

    echo sprintf(
        '[%d/%d] %s: Twinfield %s, shadow database %s%s',
        $index + 1,
        count($accounts),
        $account,
        $expected,
        $actual,
        $same ? '' : ', DIFFERENT by ' . $actual->minus($expected),
    ), PHP_EOL;
}

echo $differences === 0
    ? 'Done: the shadow database matches Twinfield.'
    : sprintf(
        'Done: %d account(s) differ. Run the incremental load, or the initial load for %d again.',
        $differences,
        $year,
    ), PHP_EOL;
```

## Run

```sh
php examples/shadow-database/validate-balances/example.php        # the last year of the company
php examples/shadow-database/validate-balances/example.php 2024   # another year
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
Balances of company 1000 in 2025: 11 general ledger account(s)
[1/11] 10100: Twinfield 17.50, shadow database 17.50
[2/11] 10101: Twinfield -2503.00, shadow database -2503.00
[3/11] 16000: Twinfield -398.23, shadow database -398.23
[4/11] 16005: Twinfield -17.50, shadow database -17.50
…
[10/11] 77004: Twinfield -324.14, shadow database -324.14
[11/11] 86090: Twinfield 601.00, shadow database 601.00
Done: the shadow database matches Twinfield.
```
