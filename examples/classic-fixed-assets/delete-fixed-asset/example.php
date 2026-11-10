<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ClassicFixedAsset\ClassicFixedAssetInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$fixedAssets = $twinfield->classicFixedAssets();

// The code from the command line, or else a new example asset.
$code = $argv[1] ?? null;
$example = $code === null;

$code ??= $fixedAssets->create(
    ClassicFixedAssetInput::create('Example delivery van')
        ->method(Example::assetMethodCode($twinfield))
        ->costCenter(Example::costCenterCode($twinfield)),
)->code;

$result = $fixedAssets->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is used in a transaction, so Twinfield hid it instead.",
}, PHP_EOL;

echo 'Status now: ', $fixedAssets->get($code)->status->value, PHP_EOL;

// A deleted or hidden asset can be made active again.
$fixedAssets->activate($code);

echo 'After activate(): ', $fixedAssets->get($code)->status->value, PHP_EOL;

// Clean up the example asset; an asset you passed stays active.
if ($example) {
    $fixedAssets->delete($code);
}
