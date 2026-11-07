<?php

declare(strict_types=1);

use SolveBeam\Twinfield\CashTransaction\CashTransactionInput;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The end-of-day report of the cash register: sales per VAT rate, including VAT.
$sales = [
    ['account' => '8000', 'vatCode' => 'VH', 'rate' => '21', 'gross' => '605.00', 'text' => 'Sales 21% VAT'],
    ['account' => '8010', 'vatCode' => 'VL', 'rate' => '9', 'gross' => '109.00', 'text' => 'Sales 9% VAT'],
];
$expenses = [
    ['account' => '4300', 'vatCode' => 'IH', 'rate' => '21', 'gross' => '24.20', 'text' => 'Coffee and milk'],
];
$opening = Amount::of('250.00');
$counted = Amount::fromString($argv[1] ?? '939.50');

// Amount without VAT: gross × 100 / (100 + rate), rounded half up to cents; and the VAT.
$split = static function (Amount $gross, Amount $rate): array {
    $net = Amount::of(bcadd(bcdiv(bcmul($gross->value, '100', 6), bcadd('100', $rate->value, 6), 6), '0.005', 2));

    return [$net, $gross->minus($net)];
};

$input = CashTransactionInput::create('KAS', '1000', new DateTimeImmutable('2026-11-03'))
    ->statement(311, startValue: $opening)
    ->description('Cash register 3 November');

foreach ($sales as $line) {
    [$net, $vat] = $split(Amount::of($line['gross']), Amount::of($line['rate']));
    $input = $input->receive(
        $line['account'],
        $net,
        vatCode: $line['vatCode'],
        vatValue: $vat,
        description: $line['text'],
    );

    echo sprintf('+ %8s = %8s + VAT %6s  %s', $line['gross'], $net, $vat, $line['text']), PHP_EOL;
}

foreach ($expenses as $line) {
    [$net, $vat] = $split(Amount::of($line['gross']), Amount::of($line['rate']));
    $input = $input->pay($line['account'], $net, vatCode: $line['vatCode'], vatValue: $vat, description: $line['text']);

    echo sprintf('- %8s = %8s + VAT %6s  %s', $line['gross'], $net, $vat, $line['text']), PHP_EOL;
}

// The cash count decides the closing balance; book the difference.
$difference = $counted->minus($input->closeValue() ?? $opening);

if (!$difference->isZero()) {
    $input = $difference->isNegative()
        ? $input->pay('8990', $difference->absolute(), description: 'Cash difference')
        : $input->receive('8990', $difference, description: 'Cash difference');

    echo 'Cash difference: ', $difference, PHP_EOL;
}

echo 'Cash: ', $opening, ' → ', $input->closeValue(), ' (counted ', $counted, ')', PHP_EOL;

if (!in_array('--save', $argv ?? [], true)) {
    echo PHP_EOL, 'Dry run; add --save to create it as provisional transaction:', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml($twinfield->companyCode() ?? '')), PHP_EOL;

    exit(0);
}

$transaction = $twinfield->cashTransactions()->create($input);

echo 'Created provisional transaction ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
