<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));

$query = $twinfield->browse(BrowseCode::GeneralLedgerDetails)
    ->columns(
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.head.date',
        'fin.trs.head.origin',
        'fin.trs.line.dim1',
        'fin.trs.line.dim1type',
        'fin.trs.line.dim2',
        'fin.trs.line.dim2type',
        'fin.trs.line.debitcredit',
        'fin.trs.line.valuesigned',
        'fin.trs.line.vatcode',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal');

$result = $query->get();

echo count($result), ' transaction line(s) in ', $year, PHP_EOL;

foreach ($result as $index => $row) {
    $dim2 = $row->nullableString('fin.trs.line.dim2');

    echo sprintf(
        '[%d/%d] %s %d line %d, %s, %s: %s (%s) %s %s %s%s',
        $index + 1,
        count($result),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->transactionLine?->line,
        $row->date('fin.trs.head.date')->format('Y-m-d'),
        $row->displayName('fin.trs.head.origin') ?? $row->string('fin.trs.head.origin'),
        $row->string('fin.trs.line.dim1'),
        $row->string('fin.trs.line.dim1type'),
        $dim2 === null ? '-' : $dim2 . ' (' . $row->string('fin.trs.line.dim2type') . ')',
        $row->string('fin.trs.line.debitcredit'),
        $row->amount('fin.trs.line.valuesigned'),
        $row->nullableString('fin.trs.line.vatcode') === null ? '' : ' VAT ' . $row->string('fin.trs.line.vatcode'),
    ), PHP_EOL;
}
