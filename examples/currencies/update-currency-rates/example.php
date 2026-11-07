<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Currency\Currency;
use SolveBeam\Twinfield\Currency\CurrencyInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$currencies = $twinfield->currencies();
$code = $argv[1] ?? Example::unusedCurrencyCode($twinfield);

$print = static function (string $label, Currency $currency): void {
    echo $label, PHP_EOL;

    foreach ($currency->rates as $rate) {
        echo '  from ', $rate->startDate->format('Y-m-d'), '  ', $rate->rate, PHP_EOL;
    }
};

$currency = $currencies->create(
    CurrencyInput::create($code, 'Example currency')
        ->rate(new DateTimeImmutable('2026-01-01'), '1.0850')
        ->rate(new DateTimeImmutable('2026-04-01'), '1.0790'),
);

try {
    $print('Before:', $currency);

    $currency = $currencies->update(
        $currency->edit()
            ->rate(new DateTimeImmutable('2026-07-01'), '1.0912')   // add
            ->rate(new DateTimeImmutable('2026-01-01'), '1.0855')   // change
            ->removeRate(new DateTimeImmutable('2026-04-01')),      // remove
    );

    $print('After:', $currency);
} finally {
    // Clean up.
    $currencies->delete($code);
}
