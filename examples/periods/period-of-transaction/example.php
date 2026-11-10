<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Period\PeriodDetails;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = Example::lastYear($twinfield);
$from = Period::fromString($argv[1] ?? $year . '/00');
$to = Period::fromString($argv[2] ?? $year . '/55');

$result = $twinfield->browse(BrowseCode::GeneralLedgerDetailsV3)
    ->columns('fin.trs.head.code', 'fin.trs.head.number', 'fin.trs.head.date', 'fin.trs.head.year', 'fin.trs.head.period')
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
