<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\BrowseData\SortOrder;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$account = $argv[2] ?? null;

$query = $twinfield->browse(BrowseCode::GeneralLedgerTransactions)
    ->columns(
        'fin.trs.head.yearperiod',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.head.status',
        'fin.trs.line.dim1',
        'fin.trs.line.basevaluesigned',
        'fin.trs.line.description',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal')
    ->sortBy('fin.trs.line.dim1')
    ->sortBy('fin.trs.head.yearperiod')
    ->sortBy('fin.trs.head.number', SortOrder::Descending);

if ($account !== null) {
    $query = $query->between('fin.trs.line.dim1', $account, $account);
}

$result = $query->get();

echo count($result), ' transaction line(s) in ', $year, $account === null ? '' : ' on account ' . $account, PHP_EOL;

foreach ($result as $index => $row) {
    echo rtrim(sprintf(
        '[%d/%d] %s %s %s %d (%s) %s %s',
        $index + 1,
        count($result),
        $row->period('fin.trs.head.yearperiod'),
        $row->string('fin.trs.line.dim1'),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->displayName('fin.trs.head.status') ?? $row->string('fin.trs.head.status'),
        $row->amount('fin.trs.line.basevaluesigned'),
        $row->nullableString('fin.trs.line.description') ?? '',
    )), PHP_EOL;
}
