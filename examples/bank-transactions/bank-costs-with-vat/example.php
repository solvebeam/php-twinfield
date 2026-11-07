<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BankTransaction\BankTransactionInput;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The bank statement only has the amount including VAT.
$gross = Amount::fromString($argv[1] ?? '12.10');
$vatRate = '21';
$vatCode = 'IH';

// Amount without VAT: gross × 100 / (100 + rate), rounded half up to cents.
$net = Amount::of(bcadd(bcdiv(bcmul($gross->value, '100', 6), bcadd('100', $vatRate, 6), 6), '0.005', 2));
$vat = $gross->minus($net);

echo 'Gross ', $gross, ' = net ', $net, ' + VAT ', $vat, ' (', $vatRate, '%)', PHP_EOL;

$input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
    ->pay('4500', $net, vatCode: $vatCode, vatValue: $vat, description: 'Bank costs November')
    // Twinfield calculates the VAT itself; allow a rounding difference of at most two cents.
    ->autoBalanceVat();

echo 'Total line: ', $input->balance()->absolute(), ' paid', PHP_EOL;

if (!in_array('--save', $argv ?? [], true)) {
    echo PHP_EOL, 'Dry run; add --save to create it as provisional transaction:', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml($twinfield->companyCode() ?? '')), PHP_EOL;

    exit(0);
}

$transaction = $twinfield->bankTransactions()->create($input);

echo 'Created provisional transaction ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
