<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$budgets = $twinfield->budgets();

// Budget code and year from the command line; defaults: the first budget and the last year.
$code = $argv[1] ?? iterator_to_array($budgets->search()->all(), false)[0]->code ?? null;
$years = $twinfield->periods()->years();
$year = (int) ($argv[2] ?? ($years === [] ? date('Y') : max($years)));

if ($code === null) {
    echo 'The company has no budgets.', PHP_EOL;

    exit;
}

$totals = $budgets->totals($code, $year)->periods(1, 12)->byProfitAndLoss();

echo 'Budget ', $code, ', ', $year, ', periods 1–12', PHP_EOL, PHP_EOL;
echo sprintf('%-10s %14s %14s %14s', 'Account', 'Budget', 'Actual', 'Difference'), PHP_EOL;

// Amounts are exact decimal strings: add them up with bcmath, not with floats.
foreach ($totals->generalLedgerAccounts() as $account) {
    $budget = '0';
    $actual = '0';

    foreach ($totals->forGeneralLedgerAccount($account) as $total) {
        $budget = bcadd($budget, $total->budget->total, 2);
        $actual = bcadd($actual, $total->actual->total, 2);
    }

    echo sprintf('%-10s %14s %14s %14s', $account, $budget, $actual, bcsub($actual, $budget, 2)), PHP_EOL;
}
