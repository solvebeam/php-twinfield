<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = Example::lastYear($twinfield);
$from = FinancialPeriod::fromString($argv[1] ?? $year . '/00');
$to = FinancialPeriod::fromString($argv[2] ?? $year . '/55');
$dayBook = $argv[3] ?? null;

$query = $twinfield->browse(BrowseCode::GeneralLedgerDetailsV3)
    ->columns(
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.head.date',
        'fin.trs.head.status',
        'fin.trs.head.relation',
        'fin.trs.head.username',
        'fin.trs.head.modified',
        'fin.trs.line.dim1',
        'fin.trs.line.dim2',
        'fin.trs.line.debitcredit',
        'fin.trs.line.valuesigned',
        'fin.trs.line.vatcode',
        'fin.trs.line.description',
    )
    ->between('fin.trs.head.yearperiod', $from, $to)
    ->equal('fin.trs.head.status', 'normal');

if ($dayBook !== null) {
    $query = $query->equal('fin.trs.head.code', $dayBook);
}

$result = $query->get();

echo count($result), ' transaction line(s) in ', $from, ' to ', $to;
echo $dayBook === null ? '' : ' of day book ' . $dayBook, PHP_EOL;

foreach ($result as $index => $row) {
    $line = $row->transactionLine;

    echo rtrim(sprintf(
        '[%d/%d] %s %d line %d, %s, %s: %s %s %s %s%s, changed %s by %s %s',
        $index + 1,
        count($result),
        $line?->dayBook,
        $line?->number,
        $line?->line,
        $row->date('fin.trs.head.date')->format('Y-m-d'),
        $row->string('fin.trs.head.status'),
        $row->string('fin.trs.line.dim1'),
        $row->nullableString('fin.trs.line.dim2') ?? '-',
        $row->string('fin.trs.line.debitcredit'),
        $row->amount('fin.trs.line.valuesigned'),
        $row->nullableString('fin.trs.line.vatcode') === null ? '' : ' VAT ' . $row->string('fin.trs.line.vatcode'),
        $row->dateTime('fin.trs.head.modified')->format('Y-m-d H:i'),
        $row->nullableString('fin.trs.head.username') ?? '-',
        $row->nullableString('fin.trs.line.description') ?? '',
    )), PHP_EOL;
}
