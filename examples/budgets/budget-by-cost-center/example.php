<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$budgets = $twinfield->budgets();

// Budget code, year and optional cost center range from the command line, e.g. `001 2025 KPL100 KPL199`.
$code = $argv[1] ?? iterator_to_array($budgets->search()->all(), false)[0]->code ?? null;
$years = $twinfield->periods()->years();
$year = (int) ($argv[2] ?? ($years === [] ? date('Y') : max($years)));

if ($code === null) {
    echo 'The company has no budgets.', PHP_EOL;

    exit;
}

$query = $budgets->totals($code, $year)->finalOnly();

if (isset($argv[3])) {
    $query = $query->dimension2($argv[3], $argv[4] ?? null);
}

foreach ($query->byCostCenter() as $total) {
    echo sprintf(
        '%2d  %-8s %-10s budget %12s  actual %12s',
        $total->period,
        $total->generalLedgerAccount,
        $total->dimension2 ?? '-',
        $total->budget->total,
        $total->actual->total,
    ), PHP_EOL;
}
