<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\LedgerAccount\LedgerAccountType;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$pattern = $argv[1] ?? '*';

$search = $twinfield->ledgerAccounts()->search($pattern)
    ->types(LedgerAccountType::Balance, LedgerAccountType::ProfitAndLoss)
    ->orderByCode();

$page = $search->limit(1)->get();

echo $page->filteredTotal, ' of ', $page->total, ' ledger accounts match ', $pattern, PHP_EOL;

foreach ($search->all() as $index => $account) {
    echo sprintf(
        '[%d/%d] %s %s (%s, subanalysis %s) %s',
        $index + 1,
        $page->filteredTotal,
        $account->code,
        $account->name,
        $account->type->value,
        $account->subanalysis->value,
        $account->id,
    ), PHP_EOL;
}
