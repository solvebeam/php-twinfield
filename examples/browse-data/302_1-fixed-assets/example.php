<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));

$query = $twinfield->browse(BrowseCode::FixedAssets)
    ->columns(
        'fin.trs.line.dim3',
        'fin.trs.line.dim3name',
        'fin.trs.line.asset.beginyear',
        'fin.trs.line.asset.beginperiod',
        'fin.trs.line.asset.depreciationterm',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.dim1',
        'fin.trs.line.basevaluesigned',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal');

$result = $query->get();

echo count($result), ' fixed asset line(s) in ', $year, PHP_EOL;

foreach ($result as $index => $row) {
    echo sprintf(
        '[%d/%d] %s %s (from %d/%02d, %d periods): %s %d %s %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim3'),
        $row->nullableString('fin.trs.line.dim3name') ?? '',
        $row->int('fin.trs.line.asset.beginyear'),
        $row->int('fin.trs.line.asset.beginperiod'),
        $row->int('fin.trs.line.asset.depreciationterm'),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->string('fin.trs.line.dim1'),
        $row->amount('fin.trs.line.basevaluesigned'),
    ), PHP_EOL;
}
