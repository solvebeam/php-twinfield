<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Rate\RateInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$rates = $twinfield->rates();
$code = $argv[1] ?? Example::unusedRateCode($twinfield);

$rate = $rates->create(
    RateInput::quantity($code, 'Example kilometres')
        ->shortName('Example km')
        ->currency('EUR')
        ->price(new DateTimeImmutable('2026-01-01'), internal: '0.15', external: '0.23'),
);

echo 'Created ', $rate->code, ' ', $rate->name, ' (', $rate->type->value, '): ';
echo $rate->latestPrice()->external, ' ', $rate->currency, ' per unit', PHP_EOL;

// Clean up: delete the example rate again.
$rates->delete($rate->code);

echo 'Deleted ', $rate->code, PHP_EOL;
