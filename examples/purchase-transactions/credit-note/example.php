<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\PurchaseTransaction\PurchaseTransactionInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$purchases = $twinfield->purchaseTransactions();

$supplier = Example::supplierCode($twinfield);
$costs = Example::profitAndLossAccount($twinfield);
$vatCode = $twinfield->vatCodes()->get(Example::purchaseVatCode($twinfield));
$date = new DateTimeImmutable('today');

// A credit note of the supplier: the same positive amounts as on an invoice. The credit note
// turns the sides around: credit cost lines and a debit total line.
$input = PurchaseTransactionInput::creditNote('INK', $supplier, 'EXAMPLE-C-' . $date->format('Ymd'), $date)
    ->dueDate($date)
    ->description('Credit note for EXAMPLE-' . $date->format('Ymd'))
    ->expense($costs, '40.00', vatCode: $vatCode, description: 'Returned goods');

echo 'Total: ', $input->total(), ' (negative: the supplier owes you money)', PHP_EOL;

if (!in_array('--save', $argv ?? [], true)) {
    echo 'Dry run; add --save to create it as provisional transaction and delete it again:', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml((string) $twinfield->companyCode())), PHP_EOL;

    exit(0);
}

try {
    $transaction = $purchases->create($input);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

echo 'Created: ', $transaction->dayBook(), ' ', $transaction->number(), ' ', $transaction->invoiceNumber,
    $transaction->isCreditNote() ? ' (credit note)' : '', ': ', $transaction->value(), PHP_EOL;

// Clean up: delete the provisional example transaction again.
$purchases->delete($transaction->dayBook(), $transaction->number(), 'php-twinfield example');

echo 'Deleted: ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
