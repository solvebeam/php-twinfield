<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$regime = isset($argv[2]) ? Regime::from($argv[2]) : null;

$query = $twinfield->browse(BrowseCode::GeneralLedgerDetailsV2)
    ->columns(
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.head.relation',
        'fin.trs.head.relationname',
        'fin.trs.line.dim1',
        'fin.trs.line.dim2',
        'fin.trs.line.valuesigned',
        'fin.trs.head.regime',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal');

if ($regime !== null) {
    $query = $query->equal('fin.trs.head.regime', $regime);
}

$result = $query->get();

echo count($result), ' transaction line(s) in ', $year, $regime === null ? '' : ', regime ' . $regime->value, PHP_EOL;

foreach ($result as $index => $row) {
    $relation = $row->nullableString('fin.trs.head.relation');

    echo sprintf(
        '[%d/%d] %s %d line %d (%s), relation %s: %s %s %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->transactionLine?->line,
        $row->string('fin.trs.head.regime'),
        $relation === null ? '-' : $relation . ' ' . ($row->nullableString('fin.trs.head.relationname') ?? ''),
        $row->string('fin.trs.line.dim1'),
        $row->nullableString('fin.trs.line.dim2') ?? '-',
        $row->amount('fin.trs.line.valuesigned'),
    ), PHP_EOL;
}
