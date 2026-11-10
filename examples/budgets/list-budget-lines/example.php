<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Budget\BudgetLineType;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$budgets = $twinfield->budgets();
$codes = [];

foreach ($budgets->annual()->all() as $annual) {
    echo $annual->budget->code, ' ', $annual->budget->name, ' ', $annual->year, ': balance ', $annual->balance, PHP_EOL;

    $lines = $budgets->lines($annual->budget->id, $annual->year)->type(BudgetLineType::Monetary);

    foreach ($lines->all() as $line) {
        if ($line->debit === '0' && $line->credit === '0') {
            continue;
        }

        $account = $line->key->generalLedgerAccountId;
        $codes[$account] ??= $twinfield->ledgerAccounts()->get($account)->code;

        echo '  ', $line->key->period, ' ', $codes[$account];
        echo ': debit ', $line->debit, ', credit ', $line->credit, PHP_EOL;
    }
}
