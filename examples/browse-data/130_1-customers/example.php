<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = Example::lastYear($twinfield);
$customer = $argv[1] ?? null;

$query = $twinfield->browse(BrowseCode::Customers)
    ->columns(
        'fin.trs.line.dim2',
        'fin.trs.line.dim2name',
        'fin.trs.head.customer.creditlimit',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.invnumber',
        'fin.trs.line.datedue',
        'fin.trs.line.basevaluesigned',
        'fin.trs.line.openbasevaluesigned',
    )
    ->between('fin.trs.head.yearperiod', '2000/00', sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal')
    ->equal('fin.trs.line.matchstatus', 'available');

if ($customer !== null) {
    $query = $query->between('fin.trs.line.dim2', $customer, $customer);
}

$result = $query->get();

echo count($result), ' open item(s)', $customer === null ? '' : ' of customer ' . $customer, PHP_EOL;

foreach ($result as $index => $row) {
    $due = $row->nullableDate('fin.trs.line.datedue');

    echo sprintf(
        '[%d/%d] %s %s (credit limit %s), %s %d invoice %s, due %s: %s, open %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim2'),
        $row->nullableString('fin.trs.line.dim2name') ?? '',
        $row->amount('fin.trs.head.customer.creditlimit'),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->nullableString('fin.trs.line.invnumber') ?? '-',
        $due?->format('Y-m-d') ?? '-',
        $row->amount('fin.trs.line.basevaluesigned'),
        $row->amount('fin.trs.line.openbasevaluesigned'),
    ), PHP_EOL;
}
