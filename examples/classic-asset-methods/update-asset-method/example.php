<?php

declare(strict_types=1);

use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\AssetMethodInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$assetMethods = $twinfield->classicAssetMethods();
$code = $argv[1] ?? Example::unusedAssetMethodCode($twinfield);

$method = $assetMethods->create(AssetMethodInput::create($code, 'Example laptops')->linear(36));

try {
    echo 'Before: ', $method->name, ', ', $method->calculationMethod->value, ' ', $method->periods, ' periods', PHP_EOL;

    $method = $assetMethods->update(
        $method->edit()
            ->name('Example laptops (updated)')
            ->reducingBalance('25'),
    );

    echo 'After:  ', $method->name, ', ', $method->calculationMethod->value, ' ', $method->percentage, '%', PHP_EOL;
} finally {
    $assetMethods->delete($code);
}
