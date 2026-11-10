<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$from = new DateTimeImmutable($year . '-01-01');
$to = new DateTimeImmutable($year . '-12-31');

$query = $twinfield->browse(BrowseCode::TimeAndExpensesBillingPerPeriod)
    ->columns(
        'teq.trs.head.year',
        'teq.trs.head.period',
        'teq.trs.head.customer',
        'teq.trs.head.project1',
        'teq.trs.line.quantity',
        'teq.trs.line.valueext',
        'ivb.trs.line.value',
    )
    ->betweenDates('teq.trs.head.date', $from, $to);

$result = $query->get();

echo count($result), ' row(s) from ', $from->format('Y-m-d'), ' to ', $to->format('Y-m-d'), PHP_EOL;

foreach ($result as $index => $row) {
    echo sprintf(
        '[%d/%d] %d period %d, customer %s, project %s: quantity %s, value %s, proposed %s',
        $index + 1,
        count($result),
        $row->int('teq.trs.head.year'),
        $row->int('teq.trs.head.period'),
        $row->nullableString('teq.trs.head.customer') ?? '-',
        $row->string('teq.trs.head.project1'),
        $row->nullableDecimal('teq.trs.line.quantity') ?? '0',
        $row->amount('teq.trs.line.valueext'),
        $row->amount('ivb.trs.line.value'),
    ), PHP_EOL;
}
