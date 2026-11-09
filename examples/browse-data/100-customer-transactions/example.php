<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$customer = $argv[1] ?? null;
$year = Example::lastYear($twinfield);

// Open items: invoices and payments that are not (fully) matched yet.
$query = $twinfield->browse(BrowseCode::CustomerTransactions)
    ->columns(
        'fin.trs.line.dim2',
        'fin.trs.line.dim2name',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.invnumber',
        'fin.trs.head.date',
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

$open = Amount::zero();

foreach ($result as $index => $row) {
    $due = $row->nullableDate('fin.trs.line.datedue');
    $open = $open->plus($row->amount('fin.trs.line.openbasevaluesigned'));

    echo sprintf(
        '[%d/%d] %s %s, %s %d invoice %s of %s, due %s: %s, open %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim2'),
        $row->nullableString('fin.trs.line.dim2name') ?? '',
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->nullableString('fin.trs.line.invnumber') ?? '-',
        $row->date('fin.trs.head.date')->format('Y-m-d'),
        $due === null ? '-' : $due->format('Y-m-d'),
        $row->amount('fin.trs.line.basevaluesigned'),
        $row->amount('fin.trs.line.openbasevaluesigned'),
    ), PHP_EOL;
}

echo 'Total open: ', $open, PHP_EOL;
