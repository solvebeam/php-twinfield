<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Rate\RateNotFoundException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first rate.
$code = $argv[1] ?? $twinfield->rates()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no rates.' . PHP_EOL);

try {
    $rate = $twinfield->rates()->get($code);
} catch (RateNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo 'Code:      ', $rate->code, PHP_EOL;
echo 'Name:      ', $rate->name, PHP_EOL;
echo 'Status:    ', $rate->status->value, $rate->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Type:      ', $rate->type->value, PHP_EOL;
echo 'Unit:      ', $rate->unit, PHP_EOL;
echo 'Currency:  ', $rate->currency, PHP_EOL;

foreach ($rate->prices as $price) {
    echo 'Price:     ', $price->from->format('Y-m-d'), ' to ', $price->until?->format('Y-m-d') ?? '…', ': ';
    echo 'internal ', $price->internal, ', external ', $price->external, PHP_EOL;
}

$today = $rate->priceOn(new DateTimeImmutable('today'));

echo 'Today:     ', $today === null ? 'no price' : $today->external, PHP_EOL;
