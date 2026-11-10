# Initial load

Step 1 of a shadow database: downloads all transaction lines of the company once, one year at a time, and stores them in a local database. After this, the [incremental load](../incremental-load/) keeps it up to date and [validate balances](../validate-balances/) checks it. This is what Twinfield asks in [Keeping a shadow database with transactions](https://php.twinfield.solvebeam.com/knowledge-base/shadow-database-with-transactions/).

- `browse(BrowseCode::GeneralLedgerDetailsV3)`: browse code 030_3, the general ledger transaction lines, as Twinfield advises for the initial load.
- `columns(…)`: only the fields you store. Twinfield returns all rows of a query at once, so every extra column makes each download bigger.
- `between('fin.trs.head.yearperiod', '2025/00', '2025/55')`: one year, periods 0 (opening balance) to 55 (year end). When a year of a large company times out, ask one period at a time: `2025/00` to `2025/00`, then `2025/01`, and so on. `yearperiod` itself can't be a column in 030_3 (server error), so the year and period are read from `fin.trs.head.year` and `fin.trs.head.period`.
- `equal('fin.trs.head.status', 'normal')`: provisional and final transactions. Without it you also get drafts, which have number `-1` and can't be told apart.
- `equal('fin.trs.head.regime', Regime::Generic)`: only the generic lines, the same lines as the generic balances of 040_1 in the validation. Leave it out for a company with regimes when you need the fiscal, commercial or economic lines too.
- Each row has a `transactionLine` (day book, number and line), which with the company is the key of the line. [`ShadowDatabase`](../../ShadowDatabase.php) stores the lines in SQLite, keyed by organisation id, company, day book, number and line (see [Keys for a shadow database](https://php.twinfield.solvebeam.com/guides/organisations/#keys-for-a-shadow-database)), and replaces all lines of a transaction at once.
- The start time is saved as "synced until": the incremental load asks for the changes from that moment.

Add `fin.trs.head.code` or `fin.trs.line.dim1` criteria to download only some day books or accounts, e.g. `equal('fin.trs.head.code', 'VRK')` or `between('fin.trs.line.dim1', '8000', '8999')`.

## Code

```php
$twinfield = Example::twinfield();

$database = ShadowDatabase::open($twinfield);
$years = isset($argv[1]) ? [(int) $argv[1]] : $twinfield->periods()->years();
$started = new DateTimeImmutable('now', new DateTimeZone('UTC'));

// Only the columns you need: Twinfield returns all rows of a query at once.
$query = $twinfield->browse(BrowseCode::GeneralLedgerDetailsV3)
    ->columns(
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.head.year',
        'fin.trs.head.period',
        'fin.trs.head.date',
        'fin.trs.head.status',
        'fin.trs.head.curcode',
        'fin.trs.head.modified',
        'fin.trs.line.dim1',
        'fin.trs.line.dim2',
        'fin.trs.line.dim3',
        'fin.trs.line.valuesigned',
        'fin.trs.line.basevaluesigned',
        'fin.trs.line.description',
    )
    ->equal('fin.trs.head.status', 'normal')
    ->equal('fin.trs.head.regime', Regime::Generic);

echo 'Initial load of company ', $twinfield->companyCode(), ': ', count($years), ' year(s)', PHP_EOL;
echo 'Shadow database: ', $database->file, PHP_EOL;

foreach ($years as $index => $year) {
    // One year at a time; split a year into periods when Twinfield times out.
    $result = $query
        ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
        ->get();

    $transactions = $database->replaceTransactions($result);

    echo sprintf(
        '[%d/%d] %d: %d line(s) of %d transaction(s)',
        $index + 1,
        count($years),
        $year,
        count($result),
        $transactions,
    ), PHP_EOL;
}

$database->markSynced($started);

echo 'Done: ', $database->countLines(), ' line(s) in the shadow database, synced until ',
    $started->format('Y-m-d H:i:s'), ' UTC', PHP_EOL;
```

## Run

```sh
php examples/shadow-database/initial-load/example.php        # all years of the company
php examples/shadow-database/initial-load/example.php 2025   # one year
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
Initial load of company 1000: 61 year(s)
Shadow database: /tmp/twinfield-shadow-database.sqlite
[1/61] 1965: 0 line(s) of 0 transaction(s)
[2/61] 1966: 0 line(s) of 0 transaction(s)
…
[46/61] 2010: 60 line(s) of 10 transaction(s)
[47/61] 2011: 145 line(s) of 25 transaction(s)
[54/61] 2018: 41 line(s) of 17 transaction(s)
[55/61] 2019: 46 line(s) of 22 transaction(s)
…
[59/61] 2023: 4 line(s) of 2 transaction(s)
[60/61] 2024: 26 line(s) of 4 transaction(s)
[61/61] 2025: 16 line(s) of 5 transaction(s)
Done: 511 line(s) in the shadow database, synced until 2026-11-09 16:38:31 UTC
```
