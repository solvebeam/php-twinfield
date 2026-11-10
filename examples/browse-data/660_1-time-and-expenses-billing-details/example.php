<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$from = new DateTimeImmutable($year . '-01-01');
$to = new DateTimeImmutable($year . '-12-31');

$query = $twinfield->browse(BrowseCode::TimeAndExpensesBillingDetails)
    ->columns(
        'teq.trs.head.date',
        'teq.trs.head.customer',
        'teq.trs.head.project1',
        'ivb.trs.head.number',
        'ivb.trs.line.source',
        'inv.trs.head.number',
        'teq.trs.line.valueext',
        'ivb.trs.line.value',
    )
    ->betweenDates('teq.trs.head.date', $from, $to);

$result = $query->get();

echo count($result), ' row(s) from ', $from->format('Y-m-d'), ' to ', $to->format('Y-m-d'), PHP_EOL;

foreach ($result as $index => $row) {
    $proposal = $row->nullableString('ivb.trs.head.number');
    $invoice = $row->nullableString('inv.trs.head.number');

    echo sprintf(
        '[%d/%d] %s customer %s project %s: %s, value %s, proposed %s%s%s',
        $index + 1,
        count($result),
        $row->date('teq.trs.head.date')->format('Y-m-d'),
        $row->nullableString('teq.trs.head.customer') ?? '-',
        $row->string('teq.trs.head.project1'),
        $row->string('ivb.trs.line.source'),
        $row->amount('teq.trs.line.valueext'),
        $row->amount('ivb.trs.line.value'),
        $proposal === null ? '' : ', proposal ' . $proposal,
        $invoice === null ? ', not invoiced' : ', invoice ' . $invoice,
    ), PHP_EOL;
}
