<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Rate\RateInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$rates = $twinfield->rates();
$code = $argv[1] ?? Example::unusedRateCode($twinfield);

// An example rate to change, deleted again at the end.
$rate = $rates->create(
    RateInput::time($code, 'Example consultancy')
        ->currency('EUR')
        ->price(new DateTimeImmutable('2026-01-01'), internal: '55', external: '95'),
);

// New prices from July: the current price ends on June 30.
$rate = $rates->update(
    $rate->edit()->newPriceFrom(new DateTimeImmutable('2026-07-01'), internal: '60', external: '105'),
);

foreach ($rate->prices as $price) {
    echo $price->from->format('Y-m-d'), ' to ', $price->until?->format('Y-m-d') ?? '…', ': ';
    echo $price->external, ' ', $rate->currency, PHP_EOL;
}

$rates->delete($rate->code);

echo 'Deleted ', $rate->code, PHP_EOL;
