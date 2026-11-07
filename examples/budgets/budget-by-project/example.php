<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$budgets = $twinfield->budgets();

// Budget code, year and optional project from the command line, e.g. `001 2025 P0001`.
$code = $argv[1] ?? iterator_to_array($budgets->search()->all(), false)[0]->code ?? null;
$years = $twinfield->periods()->years();
$year = (int) ($argv[2] ?? ($years === [] ? date('Y') : max($years)));

if ($code === null) {
    echo 'The company has no budgets.', PHP_EOL;

    exit;
}

$query = $budgets->totals($code, $year);

if (isset($argv[3])) {
    $query = $query->dimension3($argv[3]);
}

$totals = $query->byProject();

foreach ($totals->periods() as $period) {
    echo 'Period ', $period, PHP_EOL;

    foreach ($totals->forPeriod($period) as $total) {
        echo sprintf(
            '  %-8s %-10s %-10s budget %12s  actual %12s',
            $total->generalLedgerAccount,
            $total->dimension2 ?? '-',
            $total->dimension3 ?? '-',
            $total->budget->total,
            $total->actual->total,
        ), PHP_EOL;
    }
}
