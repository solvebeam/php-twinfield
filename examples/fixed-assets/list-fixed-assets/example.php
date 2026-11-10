<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\FixedAsset\FixedAssetStatus;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$search = $twinfield->fixedAssets()->search($argv[1] ?? '*')->orderBy('code');

if (isset($argv[2])) {
    $search = $search->status(FixedAssetStatus::from($argv[2]));
}

foreach ($search->all() as $asset) {
    echo sprintf(
        '%s %s: %s, book value %s of %s%s',
        $asset->code,
        $asset->description,
        $asset->status->value,
        $asset->netBookValue,
        $asset->purchaseValue,
        $asset->lastDepreciatedPeriod === null ? '' : ', depreciated up to ' . $asset->lastDepreciatedPeriod,
    ), PHP_EOL;

    if ($asset->postingErrorMessage !== null) {
        echo '  Posting error: ', $asset->postingErrorMessage, PHP_EOL;
    }
}
