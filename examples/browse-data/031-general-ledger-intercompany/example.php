<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));

$query = $twinfield->browse(BrowseCode::GeneralLedgerIntercompany)
    ->columns(
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.head.date',
        'fin.trs.line.dim1',
        'fin.trs.line.valuesigned',
        'fin.trs.line.destination.officename',
        'fin.trs.line.destination.dim1',
        'fin.trs.line.destination.dim1name',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal');

$result = $query->get();

echo count($result), ' intercompany line(s) in ', $year, PHP_EOL;

foreach ($result as $index => $row) {
    echo rtrim(sprintf(
        '[%d/%d] %s %d line %d, %s: %s %s, other side %s %s %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->transactionLine?->line,
        $row->date('fin.trs.head.date')->format('Y-m-d'),
        $row->string('fin.trs.line.dim1'),
        $row->amount('fin.trs.line.valuesigned'),
        $row->nullableString('fin.trs.line.destination.officename') ?? '-',
        $row->nullableString('fin.trs.line.destination.dim1') ?? '-',
        $row->nullableString('fin.trs.line.destination.dim1name') ?? '',
    )), PHP_EOL;
}
