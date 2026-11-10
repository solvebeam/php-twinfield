<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$book = $argv[1] ?? 'KAS';
$year = (int) ($argv[2] ?? Example::lastYear($twinfield));

$query = $twinfield->browse(BrowseCode::CashTransactions)
    ->columns(
        'fin.trs.head.code',
        'fin.trs.head.stmnumber',
        'fin.trs.head.startvalue',
        'fin.trs.head.closevalue',
        'fin.trs.head.number',
        'fin.trs.line.dim1',
        'fin.trs.line.dim1name',
        'fin.trs.line.basevaluesigned',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.bankcode', $book)
    ->equal('fin.trs.head.status', 'normal');

$result = $query->get();

echo count($result), ' line(s) of cash book ', $book, ' in ', $year, PHP_EOL;

foreach ($result as $index => $row) {
    echo sprintf(
        '[%d/%d] %s statement %d (%s to %s), %d line %d: %s %s %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.stmnumber'),
        $row->amount('fin.trs.head.startvalue'),
        $row->amount('fin.trs.head.closevalue'),
        $row->int('fin.trs.head.number'),
        $row->transactionLine?->line,
        $row->string('fin.trs.line.dim1'),
        $row->nullableString('fin.trs.line.dim1name') ?? '',
        $row->amount('fin.trs.line.basevaluesigned'),
    ), PHP_EOL;
}
