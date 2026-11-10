# Period of transaction

Shows for each transaction of a range of periods the name of its period and whether the period is open.

A transaction knows its period as a `Period`, e.g. `2025/02`: `$transaction->header->period`, or in browse data the columns `fin.trs.head.year` and `fin.trs.head.period`. The name, end date and status of that period come from the PeriodService:

- `periods()->get($period)`: one `PeriodDetails` with `period`, `name`, `endDate` and `open`. Throws a `PeriodNotFoundException` when the year or period does not exist; `find()` returns `null` then.
- `periods()->list($year)`: all periods of a year in one request. Use it for many transactions, as here: `get()` also reads the whole year.

The transactions come from browse code `030_3`, see [General ledger details](../../browse-data/030_3-general-ledger-details/). Without arguments the example uses the last year; pass a range such as `2025/01 2025/03`. Twinfield has no names for the periods of some (often older) years: `name` is `null` then.

## Code

```php
$twinfield = Example::twinfield();

$year = Example::lastYear($twinfield);
$from = Period::fromString($argv[1] ?? $year . '/00');
$to = Period::fromString($argv[2] ?? $year . '/55');

$result = $twinfield->browse(BrowseCode::GeneralLedgerDetailsV3)
    ->columns(
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.head.date',
        'fin.trs.head.year',
        'fin.trs.head.period',
    )
    ->between('fin.trs.head.yearperiod', $from, $to)
    ->equal('fin.trs.head.status', 'normal')
    ->get();

// One row per transaction line: keep the first line of each transaction.
$transactions = [];

foreach ($result as $row) {
    $key = $row->string('fin.trs.head.code') . ' ' . $row->int('fin.trs.head.number');

    $transactions[$key] ??= [
        'date' => $row->date('fin.trs.head.date'),
        'period' => Period::of($row->int('fin.trs.head.year'), $row->int('fin.trs.head.period')),
    ];
}

echo count($transactions), ' transaction(s) in ', $from, ' to ', $to, PHP_EOL;

// One PeriodService request per year, not per transaction.
/** @var array<int, array<string, PeriodDetails>> $periods */
$periods = [];

$index = 0;

foreach ($transactions as $key => $transaction) {
    $period = $transaction['period'];

    if (!isset($periods[$period->year])) {
        $periods[$period->year] = [];

        foreach ($twinfield->periods()->list($period->year) as $details) {
            $periods[$period->year][(string) $details->period] = $details;
        }
    }

    // Null when Twinfield does not list the period of the transaction.
    $details = $periods[$period->year][(string) $period] ?? null;

    echo sprintf(
        '[%d/%d] %s, %s, period %s %s (%s)',
        ++$index,
        count($transactions),
        $key,
        $transaction['date']->format('Y-m-d'),
        $period,
        $details?->name ?? '-',
        $details === null ? 'unknown period' : ($details->open ? 'open' : 'closed'),
    ), PHP_EOL;
}
```

## Run

```sh
php examples/periods/period-of-transaction/example.php
php examples/periods/period-of-transaction/example.php 2025/01 2025/03
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
5 transaction(s) in 2025/00 to 2025/55
[1/5] INK 202500001, 2025-02-08, period 2025/02 februari 2025 (open)
[2/5] INK 202500002, 2025-02-24, period 2025/02 februari 2025 (open)
[3/5] INK 202500003, 2025-02-25, period 2025/02 februari 2025 (open)
[4/5] MEMO 202500002, 2025-05-24, period 2025/06 juni 2025 (open)
[5/5] MEMO 202500003, 2025-09-01, period 2025/09 september 2025 (open)
```
