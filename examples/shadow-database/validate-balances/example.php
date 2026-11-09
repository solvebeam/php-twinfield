<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Examples\ShadowDatabase;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$database = ShadowDatabase::open($twinfield);
$year = (int) ($argv[1] ?? Example::lastYear($twinfield));

// The balance per general ledger account, summed by Twinfield.
$result = $twinfield->browse(BrowseCode::AnnualReportTotals)
    ->columns('fin.trs.line.dim1', 'fin.trs.line.basevaluesigned')
    ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal')
    ->equal('fin.trs.head.balanceregimeprompt', Regime::Generic)
    ->get();

$twinfieldBalances = [];

foreach ($result as $row) {
    $account = $row->string('fin.trs.line.dim1');
    $balance = $row->amount('fin.trs.line.basevaluesigned');
    $twinfieldBalances[$account] = ($twinfieldBalances[$account] ?? Amount::zero())->plus($balance);
}

$shadowBalances = $database->balances($year);
$accounts = array_unique([...array_keys($twinfieldBalances), ...array_keys($shadowBalances)]);
sort($accounts, SORT_STRING);

echo 'Balances of company ', $twinfield->companyCode(), ' in ', $year, ': ',
    count($accounts), ' general ledger account(s)', PHP_EOL;

$differences = 0;

foreach ($accounts as $index => $account) {
    $expected = $twinfieldBalances[$account] ?? Amount::zero();
    $actual = $shadowBalances[$account] ?? Amount::zero();
    $same = $expected->equals($actual);

    if (!$same) {
        $differences++;
    }

    echo sprintf(
        '[%d/%d] %s: Twinfield %s, shadow database %s%s',
        $index + 1,
        count($accounts),
        $account,
        $expected,
        $actual,
        $same ? '' : ', DIFFERENT by ' . $actual->minus($expected),
    ), PHP_EOL;
}

echo $differences === 0
    ? 'Done: the shadow database matches Twinfield.'
    : sprintf(
        'Done: %d account(s) differ. Run the incremental load, or the initial load for %d again.',
        $differences,
        $year,
    ), PHP_EOL;
