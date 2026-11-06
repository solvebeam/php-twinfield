<?php

declare(strict_types=1);

use SolveBeam\Twinfield\AssetMethod\AssetMethodInput;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$assetMethods = $twinfield->assetMethods();
$code = $argv[1] ?? Example::unusedAssetMethodCode($twinfield);

$assetMethods->create(AssetMethodInput::create($code, 'Example laptops')->linear(36));

$result = $assetMethods->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;

echo 'Status now: ', $assetMethods->find($code)?->status->value ?? 'gone', PHP_EOL;
