<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = Example::lastYear($twinfield);

$query = $twinfield->browse(BrowseCode::SuppliersV2)
    ->columns(
        'fin.trs.line.dim2',
        'fin.trs.line.dim2name',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.invnumber',
        'fin.trs.line.datedue',
        'fin.trs.line.openvaluesigned',
        'fin.trs.line.matchstatus',
        'fin.trs.line.payrun',
    )
    ->between('fin.trs.head.yearperiod', '2000/00', sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal')
    ->equal('fin.trs.line.matchstatus', 'available');

$result = $query->get();

echo count($result), ' open item(s)', PHP_EOL;

foreach ($result as $index => $row) {
    $due = $row->nullableDate('fin.trs.line.datedue');
    $run = $row->nullableString('fin.trs.line.payrun');

    echo sprintf(
        '[%d/%d] %s %s, %s %d invoice %s, due %s: open %s%s',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim2'),
        $row->nullableString('fin.trs.line.dim2name') ?? '',
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->nullableString('fin.trs.line.invnumber') ?? '-',
        $due === null ? '-' : $due->format('Y-m-d'),
        $row->amount('fin.trs.line.openvaluesigned'),
        $run === null ? ', not in a payment run' : ', payment run ' . $run,
    ), PHP_EOL;
}
