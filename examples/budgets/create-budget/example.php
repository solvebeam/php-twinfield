<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Budget\BudgetInput;
use SolveBeam\Twinfield\Budget\BudgetLineKey;
use SolveBeam\Twinfield\Budget\SpreadFor;
use SolveBeam\Twinfield\Budget\UnitOfMeasure;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\LedgerAccount\LedgerAccountType;
use SolveBeam\Twinfield\Period\Period;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$budgets = $twinfield->budgets();

$years = $twinfield->periods()->years();
$year = $years === [] ? (int) date('Y') : max($years);
$account = $twinfield->ledgerAccounts()->search()->types(LedgerAccountType::ProfitAndLoss)->limit(1)->get()->items[0];

$budget = $budgets->create(BudgetInput::create('EX' . random_int(100, 999), 'Example budget ' . $year));

echo 'Created budget ', $budget->code, ' ', $budget->name, PHP_EOL;

try {
    // 12000 over the periods of the year, evenly.
    $budgets->spread($budget->id, $year, UnitOfMeasure::Monetary, $account->id, '12000', SpreadFor::Credit);

    // And one period by hand.
    $key = new BudgetLineKey(Period::of($year, 12), UnitOfMeasure::Monetary, $account->id);
    $budgets->updateLine($budget->id, $key, '0', '2500');

    foreach ($budgets->lines($budget->id, $year)->all() as $line) {
        echo $line->key->period, ' ', $account->code, ': credit ', $line->credit, PHP_EOL;
    }
} finally {
    $budgets->delete($budget->id);

    echo 'Deleted budget ', $budget->code, PHP_EOL;
}
