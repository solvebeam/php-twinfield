<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Examples\ShadowDatabase;

require __DIR__ . '/../../../vendor/autoload.php';

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
