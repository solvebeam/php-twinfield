<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Currency\CurrencyInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$currencies = $twinfield->currencies();
$code = $argv[1] ?? Example::unusedCurrencyCode($twinfield);

$currencies->create(CurrencyInput::create($code, 'Example currency'));

echo 'Exists: ', $currencies->exists($code) ? 'yes' : 'no', PHP_EOL;

$currencies->delete($code);

echo 'Deleted ', $code, PHP_EOL;
echo 'Exists: ', $currencies->exists($code) ? 'yes' : 'no', PHP_EOL;
