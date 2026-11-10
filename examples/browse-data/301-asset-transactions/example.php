<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$asset = $argv[2] ?? null;

$query = $twinfield->browse(BrowseCode::AssetTransactions)
    ->columns(
        'fin.trs.line.dim3',
        'fin.trs.line.dim3name',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.dim1',
        'fin.trs.line.dim1name',
        'fin.trs.line.basevaluesigned',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year));

if ($asset !== null) {
    $query = $query->between('fin.trs.line.dim3', $asset, $asset);
}

$result = $query->get();

echo count($result), ' asset line(s) in ', $year, $asset === null ? '' : ' of asset ' . $asset, PHP_EOL;

foreach ($result as $index => $row) {
    echo sprintf(
        '[%d/%d] %s %s, %s %d: %s %s %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim3'),
        $row->nullableString('fin.trs.line.dim3name') ?? '',
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->string('fin.trs.line.dim1'),
        $row->nullableString('fin.trs.line.dim1name') ?? '',
        $row->amount('fin.trs.line.basevaluesigned'),
    ), PHP_EOL;
}
