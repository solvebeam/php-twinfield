<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$project = $argv[2] ?? null;

$query = $twinfield->browse(BrowseCode::ProjectTransactions)
    ->columns(
        'fin.trs.line.dim3',
        'fin.trs.line.dim3name',
        'fin.trs.head.yearperiod',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.head.status',
        'fin.trs.line.dim1',
        'fin.trs.line.dim2',
        'fin.trs.line.basevaluesigned',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year));

if ($project !== null) {
    $query = $query->between('fin.trs.line.dim3', $project, $project);
}

$result = $query->get();

echo count($result), ' project line(s) in ', $year, $project === null ? '' : ' of project ' . $project, PHP_EOL;

foreach ($result as $index => $row) {
    echo sprintf(
        '[%d/%d] %s %s, %s %s %d (%s): %s %s %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim3'),
        $row->nullableString('fin.trs.line.dim3name') ?? '',
        $row->period('fin.trs.head.yearperiod'),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->string('fin.trs.head.status'),
        $row->string('fin.trs.line.dim1'),
        $row->nullableString('fin.trs.line.dim2') ?? '-',
        $row->amount('fin.trs.line.basevaluesigned'),
    ), PHP_EOL;
}
