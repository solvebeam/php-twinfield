<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BankTransaction\BankTransactionInput;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// One payment from customer 1001, for several open invoices (oldest first).
$customer = '1001';
$received = Amount::fromString($argv[1] ?? '1250.00');
$openInvoices = ['2026-0039' => '400.00', '2026-0040' => '605.00', '2026-0041' => '500.00'];

$input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'));
$left = $received;

foreach ($openInvoices as $invoice => $open) {
    if (!$left->isPositive()) {
        break;
    }

    // Pay the invoice in full, or what is left of the payment.
    $amount = $left->compare($open) >= 0 ? Amount::of($open) : $left;
    $left = $left->minus($amount);

    $input = $input->receive('1300', $amount, customer: $customer, invoice: $invoice, description: $invoice);

    echo sprintf('%s: %8s of %8s%s', $invoice, $amount, $open, $amount->equals($open) ? '' : ' (partly)'), PHP_EOL;
}

if ($left->isPositive()) {
    // More than all open invoices: book the rest on the customer without invoice.
    $input = $input->receive('1300', $left, customer: $customer, description: 'Prepayment');

    echo 'Prepayment: ', $left, PHP_EOL;
}

echo 'Received ', $received, ' in ', count($input->lines()), ' lines', PHP_EOL;

if (!in_array('--save', $argv ?? [], true)) {
    echo PHP_EOL, 'Dry run; add --save to create it as provisional transaction:', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml($twinfield->companyCode() ?? '')), PHP_EOL;

    exit(0);
}

$transaction = $twinfield->bankTransactions()->create($input);

echo 'Created provisional transaction ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
