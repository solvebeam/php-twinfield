<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$to = Period::fromString($argv[1] ?? Example::lastYear($twinfield) . '/12');
$regime = Regime::from($argv[2] ?? 'Generic');

$query = $twinfield->browse(BrowseCode::AnnualReportYearToDate)
    ->columns(
        'fin.trs.line.dim1',
        'fin.trs.line.dim1name',
        'fin.trs.line.dim1type',
        'fin.trs.line.basevaluesigned',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $to->year), $to)
    ->equal('fin.trs.head.status', 'normal')
    ->equal('fin.trs.head.balanceregimeprompt', $regime);

$result = $query->get();

echo count($result), ' general ledger account(s) up to ', $to, ', regime ', $regime->value, PHP_EOL;

$totals = [];

foreach ($result as $index => $row) {
    $type = $row->string('fin.trs.line.dim1type');
    $balance = $row->amount('fin.trs.line.basevaluesigned');
    $totals[$type] = ($totals[$type] ?? Amount::zero())->plus($balance);

    echo sprintf(
        '[%d/%d] %s %s %s: %s',
        $index + 1,
        count($result),
        $type,
        $row->string('fin.trs.line.dim1'),
        $row->nullableString('fin.trs.line.dim1name') ?? '',
        $balance,
    ), PHP_EOL;
}

foreach ($totals as $type => $total) {
    echo 'Total ', $type, ': ', $total, PHP_EOL;
}
