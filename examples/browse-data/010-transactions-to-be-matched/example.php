<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));

$result = $twinfield->browse(BrowseCode::TransactionsToBeMatched)
    ->columns(
        'fin.trs.line.dim1',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.basevaluesigned',
        'fin.trs.line.openbasevaluesigned',
        'fin.trs.line.matchstatus',
    )
    ->between('fin.trs.head.yearperiod', '2000/00', sprintf('%d/55', $year))
    ->equal('fin.trs.line.matchstatus', 'available')
    ->get();

echo count($result), ' line(s) still to be matched up to ', $year, PHP_EOL;

foreach ($result as $index => $row) {
    echo sprintf(
        '[%d/%d] %s %s %d: %s, open %s (%s)',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim1'),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->amount('fin.trs.line.basevaluesigned'),
        $row->amount('fin.trs.line.openbasevaluesigned'),
        $row->displayName('fin.trs.line.matchstatus') ?? $row->string('fin.trs.line.matchstatus'),
    ), PHP_EOL;
}
