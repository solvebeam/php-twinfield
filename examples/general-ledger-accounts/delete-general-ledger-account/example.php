<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$accounts = $twinfield->profitAndLossAccounts();

// The code from the command line, or else a new example account.
$example = !isset($argv[1]);
$code = $argv[1] ?? $accounts->create(
    GeneralLedgerAccountInput::profitAndLoss(
        Example::unusedGeneralLedgerAccountCode($accounts),
        'Example account to delete',
    ),
)->code;

$result = $accounts->delete($code);

echo match ($result) {
    DeleteResult::Deleted => 'Deleted ' . $code,
    // An account used in a transaction, or linked to a VAT code or article, is hidden instead.
    DeleteResult::Hidden => 'Hidden ' . $code . ': it is in use',
}, PHP_EOL;

echo 'Status: ', $accounts->get($code)->status->value, PHP_EOL;

// Deleted and hidden accounts can be made active again.
$accounts->activate($code);

echo 'Status: ', $accounts->get($code)->status->value, PHP_EOL;

// Clean up the example account.
if ($example) {
    $accounts->delete($code);
}
