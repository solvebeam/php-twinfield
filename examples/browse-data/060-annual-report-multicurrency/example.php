<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$regime = Regime::from($argv[2] ?? 'Generic');

$query = $twinfield->browse(BrowseCode::AnnualReportTotalsMulticurrency)
    ->columns(
        'fin.trs.line.dim1',
        'fin.trs.line.dim1name',
        'fin.trs.head.curcode',
        'fin.trs.line.debitcredit',
        'fin.trs.line.valuesigned',
        'fin.trs.line.basevaluesigned',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'final')
    ->equal('fin.trs.head.balanceregimeprompt', $regime);

$result = $query->get();

echo count($result), ' total(s) per account and currency in ', $year, ', regime ', $regime->value, PHP_EOL;

foreach ($result as $index => $row) {
    echo sprintf(
        '[%d/%d] %s %s %s: %s %s (base %s)',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim1'),
        $row->nullableString('fin.trs.line.dim1name') ?? '',
        $row->string('fin.trs.line.debitcredit'),
        $row->string('fin.trs.head.curcode'),
        $row->amount('fin.trs.line.valuesigned'),
        $row->amount('fin.trs.line.basevaluesigned'),
    ), PHP_EOL;
}
