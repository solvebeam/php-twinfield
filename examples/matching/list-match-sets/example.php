<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$customer = $argv[1] ?? null;

if ($customer === null) {
    echo 'Usage: php example.php <customer>', PHP_EOL;

    exit(0);
}

$year = Example::lastYear($twinfield);

// Matched lines of the customer, with the match number that undo() needs.
$result = $twinfield->browse(BrowseCode::CustomerTransactions)
    ->columns(
        'fin.trs.line.dim2',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.invnumber',
        'fin.trs.line.basevaluesigned',
        'fin.trs.line.matchnumber',
        'fin.trs.line.matchdate',
    )
    ->between('fin.trs.head.yearperiod', '2000/00', sprintf('%d/55', $year))
    ->between('fin.trs.line.dim2', $customer, $customer)
    ->equal('fin.trs.line.matchstatus', 'matched')
    ->get();

echo count($result), ' matched line(s) of customer ', $customer, PHP_EOL;

foreach ($result as $index => $row) {
    $matchDate = $row->nullableDate('fin.trs.line.matchdate');

    echo sprintf(
        '[%d/%d] match %s on %s: %s %d line %d, invoice %s, %s',
        $index + 1,
        count($result),
        $row->nullableString('fin.trs.line.matchnumber') ?? '-',
        $matchDate?->format('Y-m-d') ?? '-',
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->transactionLine->line ?? 0,
        $row->nullableString('fin.trs.line.invnumber') ?? '-',
        $row->amount('fin.trs.line.basevaluesigned'),
    ), PHP_EOL;
}
