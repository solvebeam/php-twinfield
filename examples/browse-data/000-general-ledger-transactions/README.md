# General ledger transactions (000)

Browse code `000` (`BrowseCode::GeneralLedgerTransactions`, "Grootboekkaart"): the transaction lines per general ledger account, as on the general ledger card in Twinfield. Use it to show the lines of one account; for a full copy of all transaction lines use [030_3](../030_3-general-ledger-details/).

- `columns(…)`: unlike in 030_3, `fin.trs.head.yearperiod` can be a column here. The amount in the base currency is `fin.trs.line.basevaluesigned` (negative for credit).
- `between('fin.trs.head.yearperiod', '2025/00', '2025/55')`: a whole year, from the opening balance (period 0) to the year end (55).
- `equal('fin.trs.head.status', 'normal')`: provisional and final transactions. Without it you also get drafts.
- `between('fin.trs.line.dim1', $account, $account)`: one general ledger account.
- `sortBy()`: 000 sorts on up to three fields; here by account, period and number (newest first). Not every browse code sorts: 030_3 ignores it.
- `displayName('fin.trs.head.status')` gives the status as Twinfield shows it, in the language of the user.

Criteria: period, day book, number, status, general ledger account, amount and regime (`fin.trs.head.browseregime`). See [`browse-definitions/000.xml`](../../../browse-definitions/000.xml) and the [browse data guide](https://php-twinfield.solvebeam.com/guides/browse-data/).

## Code

```php
$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$account = $argv[2] ?? null;

$query = $twinfield->browse(BrowseCode::GeneralLedgerTransactions)
    ->columns(
        'fin.trs.head.yearperiod',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.head.status',
        'fin.trs.line.dim1',
        'fin.trs.line.basevaluesigned',
        'fin.trs.line.description',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal')
    ->sortBy('fin.trs.line.dim1')
    ->sortBy('fin.trs.head.yearperiod')
    ->sortBy('fin.trs.head.number', SortOrder::Descending);

if ($account !== null) {
    $query = $query->between('fin.trs.line.dim1', $account, $account);
}

$result = $query->get();

echo count($result), ' transaction line(s) in ', $year, $account === null ? '' : ' on account ' . $account, PHP_EOL;

foreach ($result as $index => $row) {
    echo rtrim(sprintf(
        '[%d/%d] %s %s %s %d (%s) %s %s',
        $index + 1,
        count($result),
        $row->period('fin.trs.head.yearperiod'),
        $row->string('fin.trs.line.dim1'),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->displayName('fin.trs.head.status') ?? $row->string('fin.trs.head.status'),
        $row->amount('fin.trs.line.basevaluesigned'),
        $row->nullableString('fin.trs.line.description') ?? '',
    )), PHP_EOL;
}
```

## Run

```sh
php examples/browse-data/000-general-ledger-transactions/example.php              # the last year
php examples/browse-data/000-general-ledger-transactions/example.php 2025 8000    # account 8000 in 2025
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
16 transaction line(s) in 2025
[1/16] 2025/09 10100 MEMO 202500003 (Definitief) 17.50 Test op 16/09/2025
[2/16] 2025/06 10101 MEMO 202500002 (Definitief) -2503.00 Journaalpost nr. 2, Testregel 3
[3/16] 2025/02 16000 INK 202500003 (Definitief) 76.13 Factuur 466151
[4/16] 2025/02 16000 INK 202500002 (Definitief) -362.62
[5/16] 2025/02 16000 INK 202500001 (Definitief) -111.74 Factuur 151568
…
[15/16] 2025/02 77004 INK 202500003 (Definitief) -324.14 Factuur 466151
[16/16] 2025/06 86090 MEMO 202500002 (Definitief) 601.00
```
