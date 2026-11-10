<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));

$query = $twinfield->browse(BrowseCode::TransactionSummary)
    ->columns(
        'fin.trs.head.yearperiod',
        'fin.trs.head.code',
        'fin.trs.head.count',
        'fin.trs.line.count',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year));

$result = $query->get();

echo count($result), ' day book(s) and period(s) in ', $year, PHP_EOL;

$transactions = 0;
$lines = 0;

foreach ($result as $index => $row) {
    $transactions += $row->int('fin.trs.head.count');
    $lines += $row->int('fin.trs.line.count');

    echo sprintf(
        '[%d/%d] %s %s: %d transaction(s), %d line(s)',
        $index + 1,
        count($result),
        $row->period('fin.trs.head.yearperiod'),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.count'),
        $row->int('fin.trs.line.count'),
    ), PHP_EOL;
}

echo 'Total: ', $transactions, ' transaction(s), ', $lines, ' line(s)', PHP_EOL;
