<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Rate\RateInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$rates = $twinfield->rates();
$code = $argv[1] ?? Example::unusedRateCode($twinfield);

$rates->create(RateInput::time($code, 'Example rate')->price(new DateTimeImmutable('2026-01-01'), '55', '95'));

$result = $rates->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;

echo 'Status now: ', $rates->find($code)?->status->value ?? 'gone', PHP_EOL;
