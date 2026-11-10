<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\SalesTransaction\SalesTransactionInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$sales = $twinfield->salesTransactions();

$customer = Example::customerCodes($twinfield, 1)[0];
$revenue = Example::profitAndLossAccount($twinfield);
$vatCode = $twinfield->vatCodes()->get(Example::salesVatCode($twinfield));
$date = new DateTimeImmutable('today');

// A credit note for part of an invoice: the same positive amounts as on the invoice. The
// credit note turns the sides around: debit revenue lines and a credit total line.
$input = SalesTransactionInput::creditNote('VRK', $customer, 'EXAMPLE-C-' . $date->format('Ymd'), $date)
    ->dueDate($date)
    ->description('Credit note for EXAMPLE-' . $date->format('Ymd'))
    ->revenue($revenue, '40.00', vatCode: $vatCode, description: 'Returned goods');

echo 'Total: ', $input->total(), ' (negative: the customer gets money back)', PHP_EOL;

if (!in_array('--save', $argv ?? [], true)) {
    echo 'Dry run; add --save to create it as provisional transaction and delete it again:', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml((string) $twinfield->companyCode())), PHP_EOL;

    exit(0);
}

try {
    $transaction = $sales->create($input);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

echo 'Created: ', $transaction->dayBook(), ' ', $transaction->number(), ' ', $transaction->invoiceNumber,
    $transaction->isCreditNote() ? ' (credit note)' : '', ': ', $transaction->value(), PHP_EOL;

// Clean up: delete the provisional example transaction again.
$sales->delete($transaction->dayBook(), $transaction->number(), 'twinfield-php example');

echo 'Deleted: ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
