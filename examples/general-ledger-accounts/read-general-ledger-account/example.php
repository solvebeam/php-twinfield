<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\GeneralLedgerAccount\ChildValidation;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first profit and loss account.
$code = $argv[1] ?? $twinfield->profitAndLossAccounts()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no profit and loss accounts.' . PHP_EOL);

// A code is a balance sheet or a profit and loss account: find() gives null for the other type.
$account = $twinfield->balanceSheetAccounts()->find($code)
    ?? $twinfield->profitAndLossAccounts()->find($code)
    ?? exit('No general ledger account ' . $code . PHP_EOL);

echo 'Code:          ', $account->code, PHP_EOL;
echo 'Name:          ', $account->name, PHP_EOL;
echo 'Type:          ', $account->type->label(), ' (', $account->type->value, ')', PHP_EOL;
echo 'Status:        ', $account->status->value, $account->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Matchable:     ', $account->matchable ? 'yes' : 'no', PHP_EOL;
echo 'Sub-analysis:  ', $account->subAnalysis->name, PHP_EOL;
echo 'Validations:   ', implode(', ', array_map(
    static fn (ChildValidation $validation): string => $validation->type->value . ' ' . $validation->value,
    $account->childValidations,
)) ?: '-', PHP_EOL;
echo 'VAT code:      ', $account->vatCode?->code ?? '-', $account->vatCodeFixed ? ' (fixed)' : '', PHP_EOL;
echo 'Groups:        ', implode(', ', $account->groups) ?: '-', PHP_EOL;
echo 'Modified:      ', $account->modified?->format('Y-m-d H:i') ?? '-', PHP_EOL;
