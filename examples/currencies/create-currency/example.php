<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Currency\CurrencyInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$currencies = $twinfield->currencies();
$code = $argv[1] ?? Example::unusedCurrencyCode($twinfield);

$currency = $currencies->create(
    CurrencyInput::create($code, 'Example currency')
        ->shortName('¤')
        ->rate(new DateTimeImmutable('2026-01-01'), '1.0850')
        ->rate(new DateTimeImmutable('2026-07-01'), '1.0912'),
);

try {
    echo 'Created ', $currency->code, ' (', $currency->name, ')', PHP_EOL;

    foreach ($currency->rates as $rate) {
        echo '  from ', $rate->startDate->format('Y-m-d'), '  ', $rate->rate, PHP_EOL;
    }
} finally {
    // Clean up.
    $currencies->delete($code);
}
