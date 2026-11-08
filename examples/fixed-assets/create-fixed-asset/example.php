<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\FixedAsset\FixedAssetInput;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$fixedAssets = $twinfield->fixedAssets();

$input = FixedAssetInput::create('Example delivery van')
    ->method(Example::assetMethodCode($twinfield))
    ->costCenter(Example::costCenterCode($twinfield))
    ->purchaseDate(new DateTimeImmutable('2026-03-15'))
    ->depreciateFrom(FinancialPeriod::of(2026, 4))
    ->residualValue('2500.00')
    ->freeText(1, 'WDB9066331S123456');

try {
    // Without code(), Twinfield gives the asset the next free code.
    $asset = $fixedAssets->create($input);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Created ', $asset->code, ' ', $asset->name, PHP_EOL;
echo 'Status:  ', $asset->assetStatus->name, PHP_EOL;
echo 'Method:  ', $asset->method?->code, ', ', $asset->periods ?? '-', ' periods', PHP_EOL;
echo 'From:    ', $asset->depreciateFrom, PHP_EOL;

// Clean up: delete the example asset again.
$fixedAssets->delete($asset->code);

echo 'Deleted ', $asset->code, PHP_EOL;
