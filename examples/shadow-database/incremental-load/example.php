<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Examples\ShadowDatabase;

require __DIR__ . '/../../../vendor/autoload.php';

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
