<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$from = new DateTimeImmutable($year . '-01-01');
$to = new DateTimeImmutable($year . '-12-31');

$query = $twinfield->browse(BrowseCode::TimeAndExpensesTotalsPerPeriod)
    ->columns(
        'teq.trs.head.year',
        'teq.trs.head.period',
        'teq.trs.head.user',
        'teq.trs.head.project1',
        'teq.trs.line.time',
        'teq.trs.line.timevalueext',
        'teq.trs.line.expense',
    )
    ->betweenDates('teq.trs.head.date', $from, $to);

$result = $query->get();

echo count($result), ' row(s) from ', $from->format('Y-m-d'), ' to ', $to->format('Y-m-d'), PHP_EOL;

foreach ($result as $index => $row) {
    echo sprintf(
        '[%d/%d] %d period %d, %s, project %s: %s h, %s, expenses %s',
        $index + 1,
        count($result),
        $row->int('teq.trs.head.year'),
        $row->int('teq.trs.head.period'),
        $row->string('teq.trs.head.user'),
        $row->string('teq.trs.head.project1'),
        $row->nullableDecimal('teq.trs.line.time') ?? '0',
        $row->amount('teq.trs.line.timevalueext'),
        $row->amount('teq.trs.line.expense'),
    ), PHP_EOL;
}
