<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$from = new DateTimeImmutable($year . '-01-01');
$to = new DateTimeImmutable($year . '-12-31');

$query = $twinfield->browse(BrowseCode::TimeAndExpensesTotals)
    ->columns(
        'teq.trs.head.project1',
        'teq.trs.head.project2',
        'teq.trs.head.user',
        'teq.trs.line.type',
        'teq.trs.line.rate.code',
        'teq.trs.line.billable',
        'teq.trs.line.quantity',
        'teq.trs.line.valueint',
        'teq.trs.line.valueext',
    )
    ->betweenDates('teq.trs.head.date', $from, $to);

$result = $query->get();

echo count($result), ' row(s) from ', $from->format('Y-m-d'), ' to ', $to->format('Y-m-d'), PHP_EOL;

foreach ($result as $index => $row) {
    echo sprintf(
        '[%d/%d] project %s activity %s, %s, %s rate %s%s: %s, internal %s, external %s',
        $index + 1,
        count($result),
        $row->string('teq.trs.head.project1'),
        $row->nullableString('teq.trs.head.project2') ?? '-',
        $row->string('teq.trs.head.user'),
        $row->string('teq.trs.line.type'),
        $row->nullableString('teq.trs.line.rate.code') ?? '-',
        $row->string('teq.trs.line.billable') === 'true' ? ' (billable)' : '',
        $row->nullableDecimal('teq.trs.line.quantity') ?? '0',
        $row->amount('teq.trs.line.valueint'),
        $row->amount('teq.trs.line.valueext'),
    ), PHP_EOL;
}
