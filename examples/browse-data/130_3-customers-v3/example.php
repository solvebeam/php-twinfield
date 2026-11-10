<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = Example::lastYear($twinfield);

$query = $twinfield->browse(BrowseCode::CustomersV3)
    ->columns(
        'fin.trs.head.customer.respuser',
        'fin.trs.head.customer.respusername',
        'fin.trs.line.dim2',
        'fin.trs.line.dim2name',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.invnumber',
        'fin.trs.line.datedue',
        'fin.trs.line.openbasevaluesigned',
        'fin.trs.line.payrun',
    )
    ->between('fin.trs.head.yearperiod', '2000/00', sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal')
    ->equal('fin.trs.line.matchstatus', 'available');

$result = $query->get();

echo count($result), ' open item(s)', PHP_EOL;

$perManager = [];

foreach ($result as $index => $row) {
    $manager = $row->nullableString('fin.trs.head.customer.respusername') ?? 'no credit manager';
    $due = $row->nullableDate('fin.trs.line.datedue');
    $open = $row->amount('fin.trs.line.openbasevaluesigned');
    $perManager[$manager] = ($perManager[$manager] ?? Amount::zero())->plus($open);
    $run = $row->nullableString('fin.trs.line.payrun');

    echo sprintf(
        '[%d/%d] %s: %s %s, %s %d invoice %s, due %s: open %s%s',
        $index + 1,
        count($result),
        $manager,
        $row->string('fin.trs.line.dim2'),
        $row->nullableString('fin.trs.line.dim2name') ?? '',
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->nullableString('fin.trs.line.invnumber') ?? '-',
        $due === null ? '-' : $due->format('Y-m-d'),
        $open,
        $run === null ? '' : ', collection run ' . $run,
    ), PHP_EOL;
}

foreach ($perManager as $manager => $open) {
    echo 'Open for ', $manager, ': ', $open, PHP_EOL;
}
