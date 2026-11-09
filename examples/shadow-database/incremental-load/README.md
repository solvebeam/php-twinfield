# Incremental load

Step 2 of a shadow database: run daily or weekly after the [initial load](../initial-load/) to download only what changed: the transactions added or changed since the last sync, and the transactions deleted since then.

- The query is that of the initial load, with `betweenDateTimes('fin.trs.head.modified', $since, $until)` instead of a period: all lines of the transactions changed between the two moments. Twinfield uses UTC (`yyyymmddhhmmss`); `betweenDateTimes()` converts the moments to UTC.
- A changed transaction can have more or fewer lines than before, so `ShadowDatabase::replaceTransactions()` deletes its old lines before it stores the new ones.
- Deleted transactions are not in browse data: they come from `deletedTransactions()->list(from: $since)` (the `GetDeletedTransactions` query of the article). That service filters on the day, not the time, so it returns some transactions you may have deleted already; deleting them again does no harm.
- The sync starts 5 minutes before the last "synced until", so a transaction saved during the last run is not missed. Replacing a transaction twice gives the same result.
- A transaction that goes from draft to provisional gets a number and a change date, so it comes in with the next incremental load.

Before the first initial load, the example takes the last 7 days.

## Code

```php
$twinfield = Example::twinfield();

$database = ShadowDatabase::open($twinfield);
$utc = new DateTimeZone('UTC');
$until = new DateTimeImmutable('now', $utc);

// A few minutes of overlap with the last sync: replacing a transaction twice does no harm.
$since = $database->syncedUntil()?->modify('-5 minutes');

if ($since === null) {
    $since = $until->modify('-7 days');

    echo 'No initial load yet (run initial-load first); taking the last 7 days.', PHP_EOL;
}

echo 'Incremental load of company ', $twinfield->companyCode(), ': changes from ', $since->format('Y-m-d H:i:s'),
    ' to ', $until->format('Y-m-d H:i:s'), ' UTC', PHP_EOL;

// The query of the initial load, with the change date instead of the period.
$result = $twinfield->browse(BrowseCode::GeneralLedgerDetailsV3)
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
    ->equal('fin.trs.head.regime', Regime::Generic)
    ->betweenDateTimes('fin.trs.head.modified', $since, $until)
    ->get();

$transactions = $database->replaceTransactions($result);

echo sprintf('[1/2] Added or changed: %d line(s) of %d transaction(s)', count($result), $transactions), PHP_EOL;

// Deleted transactions are not in browse data. The service filters on the day only, not the time.
$deleted = $twinfield->deletedTransactions()->list(from: $since);

echo sprintf('[2/2] Deleted since %s: %d transaction(s)', $since->format('Y-m-d'), count($deleted)), PHP_EOL;

foreach ($deleted as $index => $transaction) {
    $lines = $database->deleteTransaction($transaction->dayBook, $transaction->number);

    echo sprintf(
        '  [%d/%d] %s %d, deleted %s: %s',
        $index + 1,
        count($deleted),
        $transaction->dayBook,
        $transaction->number,
        $transaction->deletionDate->format('Y-m-d H:i'),
        $lines === 0 ? 'not in the shadow database' : sprintf('%d line(s) removed', $lines),
    ), PHP_EOL;
}

$database->markSynced($until);

echo 'Done: ', $database->countLines(), ' line(s) in the shadow database, synced until ',
    $until->format('Y-m-d H:i:s'), ' UTC', PHP_EOL;
```

## Run

```sh
php examples/shadow-database/incremental-load/example.php
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
Incremental load of company 1000: changes from 2026-11-02 05:55:00 to 2026-11-09 16:38:43 UTC
[1/2] Added or changed: 35 line(s) of 14 transaction(s)
[2/2] Deleted since 2026-11-02: 0 transaction(s)
Done: 511 line(s) in the shadow database, synced until 2026-11-09 16:38:43 UTC
```
