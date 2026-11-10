<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$dayBook = $argv[2] ?? null;

$query = $twinfield->browse(BrowseCode::TransactionList)
    ->columns(
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.head.status',
        'fin.trs.head.date',
        'fin.trs.head.inpdate',
        'fin.trs.line.dim1',
        'fin.trs.line.dim2',
        'fin.trs.line.basevaluesigned',
        'fin.trs.line.description',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal');

if ($dayBook !== null) {
    $query = $query->equal('fin.trs.head.code', $dayBook);
}

$result = $query->get();

echo count($result), ' transaction line(s) entered in ', $year;
echo $dayBook === null ? '' : ' in day book ' . $dayBook, PHP_EOL;

foreach ($result as $index => $row) {
    echo rtrim(sprintf(
        '[%d/%d] %s %d line %d (%s), %s, entered %s: %s %s %s %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->transactionLine?->line,
        $row->displayName('fin.trs.head.status') ?? $row->string('fin.trs.head.status'),
        $row->date('fin.trs.head.date')->format('Y-m-d'),
        $row->dateTime('fin.trs.head.inpdate')->format('Y-m-d H:i'),
        $row->string('fin.trs.line.dim1'),
        $row->nullableString('fin.trs.line.dim2') ?? '-',
        $row->amount('fin.trs.line.basevaluesigned'),
        $row->nullableString('fin.trs.line.description') ?? '',
    )), PHP_EOL;
}
