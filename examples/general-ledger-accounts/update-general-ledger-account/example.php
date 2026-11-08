<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\GeneralLedgerAccount\ChildValidation;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountInput;
use SolveBeam\Twinfield\GeneralLedgerAccount\SubAnalysis;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$accounts = $twinfield->balanceSheetAccounts();

$account = $accounts->create(
    GeneralLedgerAccountInput::balanceSheet(Example::unusedGeneralLedgerAccountCode($accounts), 'Suspense account'),
);

try {
    echo 'Before: ', $account->name, ', matchable: ', $account->matchable ? 'yes' : 'no',
        ', sub-analysis: ', $account->subAnalysis->name, PHP_EOL;

    // edit() starts from all current values; change only what you need.
    $account = $accounts->update(
        $account->edit()
            ->name('Suspense account bank')
            ->matchable()
            ->subAnalysis(SubAnalysis::Optional)
            ->childValidation(ChildValidation::dimensionType('DEB'))
            ->childValidation(ChildValidation::dimensionType('CRD')),
    );

    echo 'After:  ', $account->name, ', matchable: ', $account->matchable ? 'yes' : 'no',
        ', sub-analysis: ', $account->subAnalysis->name, ' (', implode(', ', array_map(
            static fn (ChildValidation $validation): string => $validation->value,
            $accounts->get($account->code)->childValidations,
        )), ')', PHP_EOL;
} finally {
    // Clean up: delete the example account again.
    $accounts->delete($account->code);
}
