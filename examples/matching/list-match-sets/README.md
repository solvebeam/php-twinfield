# List match sets

The matched lines of a customer, with the match number and the match date, from browse data (browse code 100, customer transactions). The match number is what [Undo match](../undo-match/) needs.

- `equal('fin.trs.line.matchstatus', 'matched')`: only matched lines. The other values are `available`, `proposed` and `notmatchable`.
- `$row->transactionLine` is the day book, number and line of the row (`<key>`). Pass it to `MatchInput::lineReference()` to match an open line.

For suppliers, use browse code 200 (`BrowseCode::SupplierTransactions`) with the same fields.

## Code

```php
$twinfield = Example::twinfield();

$customer = $argv[1] ?? null;

if ($customer === null) {
    echo 'Usage: php example.php <customer>', PHP_EOL;

    exit(0);
}

$year = Example::lastYear($twinfield);

// Matched lines of the customer, with the match number that undo() needs.
$result = $twinfield->browse(BrowseCode::CustomerTransactions)
    ->columns(
        'fin.trs.line.dim2',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.invnumber',
        'fin.trs.line.basevaluesigned',
        'fin.trs.line.matchnumber',
        'fin.trs.line.matchdate',
    )
    ->between('fin.trs.head.yearperiod', '2000/00', sprintf('%d/55', $year))
    ->between('fin.trs.line.dim2', $customer, $customer)
    ->equal('fin.trs.line.matchstatus', 'matched')
    ->get();

echo count($result), ' matched line(s) of customer ', $customer, PHP_EOL;

foreach ($result as $index => $row) {
    $matchDate = $row->nullableDate('fin.trs.line.matchdate');

    echo sprintf(
        '[%d/%d] match %s on %s: %s %d line %d, invoice %s, %s',
        $index + 1,
        count($result),
        $row->nullableString('fin.trs.line.matchnumber') ?? '-',
        $matchDate === null ? '-' : $matchDate->format('Y-m-d'),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->transactionLine->line ?? 0,
        $row->nullableString('fin.trs.line.invnumber') ?? '-',
        $row->amount('fin.trs.line.basevaluesigned'),
    ), PHP_EOL;
}
```

## Run

```sh
php examples/matching/list-match-sets/example.php 1000
```

The query runs live, but the test companies have no matched lines yet: the output below is illustrative.

```text
2 matched line(s) of customer 1000
[1/2] match 12 on 2026-11-10: KAS 202600014 line 2, invoice -, -119.00
[2/2] match 12 on 2026-11-10: VRK 202600070 line 1, invoice 2026-0070, 121.00
```
