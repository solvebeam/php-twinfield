<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));

$query = $twinfield->browse(BrowseCode::CostCenters)
    ->columns(
        'fin.trs.line.dim2',
        'fin.trs.line.dim2name',
        'fin.trs.line.dim1',
        'fin.trs.line.dim1name',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.basevaluesigned',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year));

$result = $query->get();

echo count($result), ' cost centre line(s) in ', $year, PHP_EOL;

$totals = [];

foreach ($result as $index => $row) {
    $costCenter = $row->string('fin.trs.line.dim2');
    $value = $row->amount('fin.trs.line.basevaluesigned');
    $totals[$costCenter] = ($totals[$costCenter] ?? Amount::zero())->plus($value);

    echo sprintf(
        '[%d/%d] %s %s: %s %s, %s %d %s',
        $index + 1,
        count($result),
        $costCenter,
        $row->nullableString('fin.trs.line.dim2name') ?? '',
        $row->string('fin.trs.line.dim1'),
        $row->nullableString('fin.trs.line.dim1name') ?? '',
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $value,
    ), PHP_EOL;
}

foreach ($totals as $costCenter => $total) {
    echo 'Total ', $costCenter, ': ', $total, PHP_EOL;
}
