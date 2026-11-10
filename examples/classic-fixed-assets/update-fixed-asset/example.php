<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ClassicFixedAsset\ClassicFixedAssetField;
use SolveBeam\Twinfield\ClassicFixedAsset\ClassicFixedAssetInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$fixedAssets = $twinfield->classicFixedAssets();

$created = $fixedAssets->create(
    ClassicFixedAssetInput::create('Example delivery van')
        ->method(Example::assetMethodCode($twinfield))
        ->costCenter(Example::costCenterCode($twinfield)),
);

try {
    $asset = $fixedAssets->get($created->code);
    echo 'Before: ', $asset->name, ', residual value ', $asset->residualValue, PHP_EOL;

    // edit() leaves out what Twinfield has locked, e.g. the begin period of an active asset.
    $input = $asset->edit()
        ->name('Example delivery van (updated)')
        ->residualValue('1000.00')
        ->freeText(2, 'Lease contract 2026-001');

    if (!$asset->isLocked(ClassicFixedAssetField::Periods)) {
        $input = $input->periods(48);
    }

    $asset = $fixedAssets->update($input);
    echo 'After:  ', $asset->name, ', residual value ', $asset->residualValue, PHP_EOL;
    echo 'Periods: ', $asset->periods ?? '-', PHP_EOL;
} finally {
    $fixedAssets->delete($created->code);
}
