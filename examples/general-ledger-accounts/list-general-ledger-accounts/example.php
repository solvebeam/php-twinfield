<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// "bas" or "pnl" from the command line (default pnl), and an optional pattern, e.g. "8*".
$accounts = ($argv[1] ?? 'pnl') === 'bas' ? $twinfield->balanceSheetAccounts() : $twinfield->profitAndLossAccounts();

$search = $accounts->search($argv[2] ?? '*')->inCode()->includeHidden();

echo ucfirst($accounts->type()->label()), 's: ', $search->count(), PHP_EOL;

foreach ($search->limit(10)->get() as $account) {
    echo $account->code, ' ', $account->name, $account->hidden ? ' (hidden)' : '', PHP_EOL;
}
