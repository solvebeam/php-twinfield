<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Currency\CurrencyNotFoundException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first currency.
$code = $argv[1] ?? $twinfield->currencies()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no currencies.' . PHP_EOL);

try {
    $currency = $twinfield->currencies()->get($code);
} catch (CurrencyNotFoundException) {
    echo 'Currency ', $code, ' not found.', PHP_EOL;

    exit(1);
}

echo 'Code:        ', $currency->code, PHP_EOL;
echo 'Name:        ', $currency->name, PHP_EOL;
echo 'Short name:  ', $currency->shortName ?? '-', PHP_EOL;
echo 'Status:      ', $currency->status->value, PHP_EOL;
echo PHP_EOL;

echo 'Rates:', PHP_EOL;

foreach ($currency->rates as $rate) {
    echo '  from ', $rate->startDate->format('Y-m-d'), '  ', $rate->rate, PHP_EOL;
}

echo PHP_EOL;
echo 'Rate today:  ', $currency->rateOn(new DateTimeImmutable('today'))->rate ?? '-', PHP_EOL;
