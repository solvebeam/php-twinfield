<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = Example::lastYear($twinfield);
$supplier = $argv[1] ?? null;

$query = $twinfield->browse(BrowseCode::Suppliers)
    ->columns(
        'fin.trs.line.dim2',
        'fin.trs.line.dim2name',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.invnumber',
        'fin.trs.line.datedue',
        'fin.trs.line.basevaluesigned',
        'fin.trs.line.openbasevaluesigned',
        'fin.trs.line.matchstatus',
    )
    ->between('fin.trs.head.yearperiod', '2000/00', sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal')
    ->equal('fin.trs.line.matchstatus', 'available');

if ($supplier !== null) {
    $query = $query->between('fin.trs.line.dim2', $supplier, $supplier);
}

$result = $query->get();

echo count($result), ' open item(s)', $supplier === null ? '' : ' of supplier ' . $supplier, PHP_EOL;

$open = Amount::zero();

foreach ($result as $index => $row) {
    $due = $row->nullableDate('fin.trs.line.datedue');
    $open = $open->plus($row->amount('fin.trs.line.openbasevaluesigned'));

    echo sprintf(
        '[%d/%d] %s %s, %s %d invoice %s, due %s: %s, open %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim2'),
        $row->nullableString('fin.trs.line.dim2name') ?? '',
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->nullableString('fin.trs.line.invnumber') ?? '-',
        $due?->format('Y-m-d') ?? '-',
        $row->amount('fin.trs.line.basevaluesigned'),
        $row->amount('fin.trs.line.openbasevaluesigned'),
    ), PHP_EOL;
}

echo 'Total open: ', $open, PHP_EOL;
