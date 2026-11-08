<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\GeneralLedgerAccount\ChildValidation;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountInput;
use SolveBeam\Twinfield\GeneralLedgerAccount\SubAnalysis;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$accounts = $twinfield->profitAndLossAccounts();

// A free code in the format of the company, e.g. 8999.
$code = Example::unusedGeneralLedgerAccountCode($accounts);

$input = GeneralLedgerAccountInput::profitAndLoss($code, 'Revenue consultancy')
    ->shortName('Consultancy')
    // Every line needs a cost center (dimension 2).
    ->subAnalysis(SubAnalysis::Required)
    ->childValidation(ChildValidation::dimensionType('KPL'));

try {
    $account = $accounts->create($input);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Created ', $account->code, ' ', $account->name, PHP_EOL;
echo 'Sub-analysis: ', $account->subAnalysis->name, ', ', $account->childValidations[0]->value ?? '-', PHP_EOL;

// Clean up: delete the example account again.
$accounts->delete($account->code);

echo 'Deleted ', $account->code, PHP_EOL;
