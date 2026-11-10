<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$fixedAssets = $twinfield->fixedAssets();

$id = $argv[1] ?? $fixedAssets->search()->limit(1)->get()->items[0]->id
    ?? throw new RuntimeException('The company has no fixed assets in the new fixed assets module.');

$asset = $fixedAssets->get($id);

echo $asset->code, ' ', $asset->description, ' (', $asset->status->value, ')', PHP_EOL;
echo 'Purchase value ', $asset->purchaseValue, ', book value ', $asset->netBookValue, ' ', $asset->currency, PHP_EOL;
echo 'Invested ', $asset->investmentDate->format('Y-m-d');
echo ', in use since ', $asset->firstUseDate->format('Y-m-d'), PHP_EOL;
echo 'Purchase value account ', $asset->purchaseValueAccount->code, ' ', $asset->purchaseValueAccount->name, PHP_EOL;

foreach ($asset->depreciationSettings as $settings) {
    foreach ($settings->methods as $method) {
        echo sprintf(
            'Depreciation %s from %s: %s, lifetime %s (%s), remainder value %s',
            $settings->regime->value,
            $method->from,
            $method->method->value,
            $method->lifetime,
            $method->lifetimeType->value,
            $settings->remainderValue,
        ), PHP_EOL;
    }
}

foreach ($asset->classes as $class) {
    echo 'Class from ', $class->year, ': ', $fixedAssets->getClass($class->id)->name, PHP_EOL;
}

echo PHP_EOL, 'History:', PHP_EOL;

foreach ($fixedAssets->actions($asset->id) as $action) {
    $transactions = array_map(
        static fn ($transaction): string => $transaction->dayBook . ' ' . $transaction->number,
        $action->transactions,
    );

    echo sprintf(
        '%s %s by %s%s',
        $action->timestamp->format('Y-m-d H:i'),
        $action->type->value,
        $action->user,
        $transactions === [] ? '' : ': ' . implode(', ', array_unique($transactions)),
    ), PHP_EOL;
}
