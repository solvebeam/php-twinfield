<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$customer = $argv[1] ?? null;

$query = $twinfield->browse(BrowseCode::CreditManagement)
    ->columns(
        'fin.trs.line.dim2',
        'fin.trs.line.dim2name',
        'fin.trs.line.invnumber',
        'fin.trs.line.datedue',
        'fin.trs.line.openbasevaluesigned',
        'fin.trs.line.actioncode',
        'fin.trs.line.actionname',
        'fin.trs.line.actiondate',
        'fin.trs.line.actionvalue',
        'fin.trs.line.actionusername',
    );

if ($customer !== null) {
    $query = $query->between('fin.trs.line.dim2', $customer, $customer);
}

$result = $query->get();

echo count($result), ' credit management action(s)', $customer === null ? '' : ' of customer ' . $customer, PHP_EOL;

foreach ($result as $index => $row) {
    $due = $row->nullableDate('fin.trs.line.datedue');
    $date = $row->nullableDate('fin.trs.line.actiondate');

    echo sprintf(
        '[%d/%d] %s %s invoice %s, due %s, open %s: %s %s on %s, %s by %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim2'),
        $row->nullableString('fin.trs.line.dim2name') ?? '',
        $row->nullableString('fin.trs.line.invnumber') ?? '-',
        $due === null ? '-' : $due->format('Y-m-d'),
        $row->amount('fin.trs.line.openbasevaluesigned'),
        $row->nullableString('fin.trs.line.actioncode') ?? '-',
        $row->nullableString('fin.trs.line.actionname') ?? '',
        $date === null ? '-' : $date->format('Y-m-d'),
        $row->amount('fin.trs.line.actionvalue'),
        $row->nullableString('fin.trs.line.actionusername') ?? '-',
    ), PHP_EOL;
}
