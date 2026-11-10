# General ledger details (030_3)

Browse code `030_3` (`BrowseCode::GeneralLedgerDetailsV3`, "Grootboek (details) (v3)"): every transaction line with all its details, such as the relation, VAT code, user and change date. This is the browse code Twinfield advises for a [shadow database](../../shadow-database/initial-load/). `030_1` and `030_2` are older versions with fewer fields.

- `between('fin.trs.head.yearperiod', $from, $to)` with `Period` values: a range of periods. In 030_3 the period can only be a criterion: as column it gives a server error. Use `fin.trs.head.year` and `fin.trs.head.period` as columns instead.
- `equal('fin.trs.head.status', 'normal')`: provisional and final. Without it you also get drafts, with number `-1`.
- `equal('fin.trs.head.code', $dayBook)`: one day book, e.g. `VRK`.
- `$row->transactionLine`: the day book, number and line number of the row, e.g. to read the whole transaction with `$row->transactionLine->transaction()`.
- `fin.trs.head.modified` is the last change of the transaction, a `Datetime` in UTC: `dateTime()`.

Criteria: period, status, input date, change date, the three dimensions, regime and reporting structure. 030_3 ignores sort fields. See [`browse-definitions/030_3.xml`](../../../browse-definitions/030_3.xml) and the [browse data guide](https://php-twinfield.solvebeam.com/guides/browse-data/).

## Code

```php
$twinfield = Example::twinfield();

$year = Example::lastYear($twinfield);
$from = Period::fromString($argv[1] ?? $year . '/00');
$to = Period::fromString($argv[2] ?? $year . '/55');
$dayBook = $argv[3] ?? null;

$query = $twinfield->browse(BrowseCode::GeneralLedgerDetailsV3)
    ->columns(
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.head.date',
        'fin.trs.head.status',
        'fin.trs.head.relation',
        'fin.trs.head.username',
        'fin.trs.head.modified',
        'fin.trs.line.dim1',
        'fin.trs.line.dim2',
        'fin.trs.line.debitcredit',
        'fin.trs.line.valuesigned',
        'fin.trs.line.vatcode',
        'fin.trs.line.description',
    )
    ->between('fin.trs.head.yearperiod', $from, $to)
    ->equal('fin.trs.head.status', 'normal');

if ($dayBook !== null) {
    $query = $query->equal('fin.trs.head.code', $dayBook);
}

$result = $query->get();

echo count($result), ' transaction line(s) in ', $from, ' to ', $to;
echo $dayBook === null ? '' : ' of day book ' . $dayBook, PHP_EOL;

foreach ($result as $index => $row) {
    $line = $row->transactionLine;

    echo rtrim(sprintf(
        '[%d/%d] %s %d line %d, %s, %s: %s %s %s %s%s, changed %s by %s %s',
        $index + 1,
        count($result),
        $line?->dayBook,
        $line?->number,
        $line?->line,
        $row->date('fin.trs.head.date')->format('Y-m-d'),
        $row->string('fin.trs.head.status'),
        $row->string('fin.trs.line.dim1'),
        $row->nullableString('fin.trs.line.dim2') ?? '-',
        $row->string('fin.trs.line.debitcredit'),
        $row->amount('fin.trs.line.valuesigned'),
        $row->nullableString('fin.trs.line.vatcode') === null ? '' : ' VAT ' . $row->string('fin.trs.line.vatcode'),
        $row->dateTime('fin.trs.head.modified')->format('Y-m-d H:i'),
        $row->nullableString('fin.trs.head.username') ?? '-',
        $row->nullableString('fin.trs.line.description') ?? '',
    )), PHP_EOL;
}
```

## Run

```sh
php examples/browse-data/030_3-general-ledger-details/example.php                       # the last year
php examples/browse-data/030_3-general-ledger-details/example.php 2025/01 2025/03 VRK   # day book VRK, periods 1 to 3
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
16 transaction line(s) in 2025/00 to 2025/55
[1/16] INK 202500001 line 1, 2025-02-08, final: 16000 2005 credit -111.74, changed 2026-11-03 13:06 by Jan de Vries Factuur 151568
[2/16] INK 202500001 line 2, 2025-02-08, final: 46035 - debit 111.74, changed 2026-11-03 13:06 by Jan de Vries Factuur 151568
[3/16] INK 202500002 line 1, 2025-02-24, final: 16000 2005 credit -362.62, changed 2025-02-25 15:32 by Piet de Boer
[4/16] INK 202500002 line 2, 2025-02-24, final: 46035 - debit 362.62, changed 2025-02-25 15:32 by Piet de Boer
[5/16] INK 202500003 line 1, 2025-02-25, final: 16000 2007 debit 76.13, changed 2025-02-25 17:51 by Anna K. Factuur 466151
…
[16/16] MEMO 202500003 line 2, 2025-09-01, final: 16005 - credit -17.50, changed 2026-11-03 13:06 by Jan de Vries Test op 16/09/2025
```
