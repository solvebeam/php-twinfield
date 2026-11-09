# Annual report totals (040_1)

Browse code `040_1` (`BrowseCode::AnnualReportTotals`, "Annual Report"): the balance per general ledger account, summed by Twinfield. Twinfield groups the lines by the columns you ask for, so ask only for the columns you want totals by. The [validate balances](../../shadow-database/validate-balances/) example uses it to check a shadow database.

- `fin.trs.line.dim1type` is `BAS` for balance sheet accounts and `PNL` for profit and loss accounts; `fin.trs.line.dim1group1` is the first group of the account.
- `equal('fin.trs.head.status', 'final')`: only final transactions. Use `normal` to include provisional ones.
- `equal('fin.trs.head.balanceregimeprompt', $regime)`: the regime is required in 040_1; Twinfield uses `Generic` when you leave it out. `Fiscal`, `Commercial` and `Economic` give the lines of that regime plus the generic lines.
- Twinfield can return more than one row per account, so the example adds them up.
- The rows are totals: `$row->transactionLine` is null.

Criteria: period, status, the three dimensions, regime and reporting structure. See [`browse-definitions/040_1.xml`](../../../browse-definitions/040_1.xml) and the [browse data guide](https://php-twinfield.solvebeam.com/guides/browse-data/). `050_1` gives year-to-date totals, `060` totals per currency.

## Code

```php
$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$regime = Regime::from($argv[2] ?? 'Generic');

$result = $twinfield->browse(BrowseCode::AnnualReportTotals)
    ->columns(
        'fin.trs.line.dim1',
        'fin.trs.line.dim1name',
        'fin.trs.line.dim1type',
        'fin.trs.line.dim1group1',
        'fin.trs.line.basevaluesigned',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'final')
    ->equal('fin.trs.head.balanceregimeprompt', $regime)
    ->get();

// Twinfield can give more than one row per account: add them up.
$accounts = [];

foreach ($result as $row) {
    $code = $row->string('fin.trs.line.dim1');

    $accounts[$code] ??= [
        'type' => $row->string('fin.trs.line.dim1type'),
        'name' => $row->nullableString('fin.trs.line.dim1name') ?? '',
        'group' => $row->nullableString('fin.trs.line.dim1group1') ?? '-',
        'balance' => Amount::zero(),
    ];
    $balance = $row->amount('fin.trs.line.basevaluesigned');
    $accounts[$code]['balance'] = $accounts[$code]['balance']->plus($balance);
}

ksort($accounts, SORT_STRING);

echo count($accounts), ' general ledger account(s) in ', $year, ', final transactions, regime ', $regime->value,
    PHP_EOL;

$totals = [];
$index = 0;

foreach ($accounts as $code => $account) {
    $totals[$account['type']] = ($totals[$account['type']] ?? Amount::zero())->plus($account['balance']);

    echo sprintf(
        '[%d/%d] %s %s %s (group %s): %s',
        ++$index,
        count($accounts),
        $account['type'],
        $code,
        $account['name'],
        $account['group'],
        $account['balance'],
    ), PHP_EOL;
}

foreach ($totals as $type => $total) {
    echo 'Total ', $type, ': ', $total, PHP_EOL;
}
```

## Run

```sh
php examples/browse-data/040_1-annual-report-totals/example.php              # the last year, generic
php examples/browse-data/040_1-annual-report-totals/example.php 2025 Fiscal   # fiscal regime
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
11 general ledger account(s) in 2025, final transactions, regime Generic
[1/11] BAS 10100 Kruisposten (group 1): 17.50
[2/11] BAS 10101 Kruisposten kas (group 1): -2503.00
[3/11] BAS 16000 Handelscrediteuren (group 2): -398.23
[4/11] BAS 16005 Nog te ontvangen facturen (group 2): -17.50
[5/11] BAS 16627 BTW Voorbelasting (group 2): -70.01
…
[11/11] PNL 86090 Bankkosten en provisie (group 3): 601.00
Total BAS: -2653.22
Total PNL: 2653.22
```
