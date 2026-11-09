<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? Example::lastYear($twinfield));
$regime = Regime::from($argv[2] ?? 'Generic');

$result = $twinfield->browse(BrowseCode::AnnualReportTotals)
    ->columns(
        'fin.trs.line.dim1',
        'fin.trs.line.dim1name',
        'fin.trs.line.dim1type',
        'fin.trs.line.dim1group1',
        'fin.trs.line.basevaluesigned',
    )
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'final')
    ->equal('fin.trs.head.balanceregimeprompt', $regime)
    ->get();

// Twinfield can give more than one row per account: add them up.
$accounts = [];

foreach ($result as $row) {
    $code = $row->string('fin.trs.line.dim1');

    $accounts[$code] ??= [
        'type' => $row->string('fin.trs.line.dim1type'),
        'name' => $row->nullableString('fin.trs.line.dim1name') ?? '',
        'group' => $row->nullableString('fin.trs.line.dim1group1') ?? '-',
        'balance' => Amount::zero(),
    ];
    $balance = $row->amount('fin.trs.line.basevaluesigned');
    $accounts[$code]['balance'] = $accounts[$code]['balance']->plus($balance);
}

ksort($accounts, SORT_STRING);

echo count($accounts), ' general ledger account(s) in ', $year, ', final transactions, regime ', $regime->value,
    PHP_EOL;

$totals = [];
$index = 0;

foreach ($accounts as $code => $account) {
    $totals[$account['type']] = ($totals[$account['type']] ?? Amount::zero())->plus($account['balance']);

    echo sprintf(
        '[%d/%d] %s %s %s (group %s): %s',
        ++$index,
        count($accounts),
        $account['type'],
        $code,
        $account['name'],
        $account['group'],
        $account['balance'],
    ), PHP_EOL;
}

foreach ($totals as $type => $total) {
    echo 'Total ', $type, ': ', $total, PHP_EOL;
}
