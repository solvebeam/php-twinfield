<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));

$query = $twinfield->browse(BrowseCode::TimeAndExpensesMulticurrency)
    ->columns(
        'fin.trs.line.dim3',
        'fin.trs.line.dim3name',
        'fin.trs.line.dim2',
        'fin.trs.line.dim1',
        'fin.trs.head.curcode',
        'fin.trs.line.valuesigned',
        'fin.trs.line.basevaluesigned',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal');

$result = $query->get();

echo count($result), ' total(s) per project in ', $year, PHP_EOL;

foreach ($result as $index => $row) {
    echo sprintf(
        '[%d/%d] project %s %s, cost centre %s, account %s: %s %s (base %s)',
        $index + 1,
        count($result),
        $row->nullableString('fin.trs.line.dim3') ?? '-',
        $row->nullableString('fin.trs.line.dim3name') ?? '',
        $row->nullableString('fin.trs.line.dim2') ?? '-',
        $row->string('fin.trs.line.dim1'),
        $row->string('fin.trs.head.curcode'),
        $row->amount('fin.trs.line.valuesigned'),
        $row->amount('fin.trs.line.basevaluesigned'),
    ), PHP_EOL;
}
