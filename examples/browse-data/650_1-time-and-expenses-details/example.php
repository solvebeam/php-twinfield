<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$from = new DateTimeImmutable($year . '-01-01');
$to = new DateTimeImmutable($year . '-12-31');

$query = $twinfield->browse(BrowseCode::TimeAndExpensesDetails)
    ->columns(
        'teq.trs.head.date',
        'teq.trs.head.user',
        'teq.trs.head.customer',
        'teq.trs.head.project1',
        'teq.trs.head.project2',
        'teq.trs.head.status',
        'teq.trs.line.time',
        'teq.trs.line.time_nb',
        'teq.trs.line.expense',
        'teq.trs.line.description',
    )
    ->betweenDates('teq.trs.head.date', $from, $to);

$result = $query->get();

echo count($result), ' row(s) from ', $from->format('Y-m-d'), ' to ', $to->format('Y-m-d'), PHP_EOL;

foreach ($result as $index => $row) {
    echo rtrim(sprintf(
        '[%d/%d] %s %s, customer %s, project %s activity %s (%s): %s h, %s h not billable, expenses %s %s',
        $index + 1,
        count($result),
        $row->date('teq.trs.head.date')->format('Y-m-d'),
        $row->string('teq.trs.head.user'),
        $row->nullableString('teq.trs.head.customer') ?? '-',
        $row->string('teq.trs.head.project1'),
        $row->nullableString('teq.trs.head.project2') ?? '-',
        $row->string('teq.trs.head.status'),
        $row->nullableDecimal('teq.trs.line.time') ?? '0',
        $row->nullableDecimal('teq.trs.line.time_nb') ?? '0',
        $row->amount('teq.trs.line.expense'),
        $row->nullableString('teq.trs.line.description') ?? '',
    )), PHP_EOL;
}
