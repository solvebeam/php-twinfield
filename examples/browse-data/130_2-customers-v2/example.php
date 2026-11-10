<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = Example::lastYear($twinfield);
$since = new DateTimeImmutable($argv[1] ?? '-30 days');

$query = $twinfield->browse(BrowseCode::CustomersV2)
    ->columns(
        'fin.trs.line.dim2',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.invnumber',
        'fin.trs.line.valuesigned',
        'fin.trs.line.openvaluesigned',
        'fin.trs.line.matchstatus',
        'fin.trs.line.matchdate',
        'fin.trs.line.modified',
    )
    ->between('fin.trs.head.yearperiod', '2000/00', sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal')
    ->betweenDateTimes('fin.trs.line.modified', $since, new DateTimeImmutable());

$result = $query->get();

echo count($result), ' customer line(s) changed since ', $since->format('Y-m-d H:i'), PHP_EOL;

foreach ($result as $index => $row) {
    $paid = $row->nullableDate('fin.trs.line.matchdate');

    echo sprintf(
        '[%d/%d] %s %s %d invoice %s: %s, open %s, %s%s, changed %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim2'),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->nullableString('fin.trs.line.invnumber') ?? '-',
        $row->amount('fin.trs.line.valuesigned'),
        $row->amount('fin.trs.line.openvaluesigned'),
        $row->string('fin.trs.line.matchstatus'),
        $paid === null ? '' : ' on ' . $paid->format('Y-m-d'),
        $row->dateTime('fin.trs.line.modified')->format('Y-m-d H:i'),
    ), PHP_EOL;
}
