<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\PurchaseTransaction\PurchaseTransactionInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$purchases = $twinfield->purchaseTransactions();

// In your own application: your purchase day book, supplier, cost account and VAT code.
$supplier = Example::supplierCode($twinfield);
$costs = Example::profitAndLossAccount($twinfield);
$vatCode = $twinfield->vatCodes()->get(Example::purchaseVatCode($twinfield));
$date = new DateTimeImmutable('today');

// Book a received invoice with the invoice number of the supplier. With a VatCode, the VAT is
// calculated with its percentage on the invoice date, so the total line matches Twinfield.
$input = PurchaseTransactionInput::invoice('INK', $supplier, 'EXAMPLE-' . $date->format('Ymd'), $date)
    ->dueDate($date->modify('+14 days'))
    ->expense($costs, '100.00', vatCode: $vatCode, description: 'Example purchase');

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

echo 'Created:  ', $transaction->dayBook(), ' ', $transaction->number(), ' (', $transaction->header->status->name, ')', PHP_EOL;
echo 'Invoice:  ', $transaction->invoiceNumber, ', due ', $transaction->dueDate?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Supplier: ', $transaction->supplier()->code, ' ', $transaction->supplier()->name, PHP_EOL;
echo 'Total:    ', $transaction->value(), ' including ', $transaction->total->vatTotal, ' VAT, open ',
    $transaction->total->openBaseValue, PHP_EOL;

foreach ($transaction->details as $line) {
    echo '  detail ', $line->dim1->code, ' ', $line->debitCredit->value, ' ', $line->value,
        ' ', $line->vatCode, ' ', $line->vatValue, PHP_EOL;
}

foreach ($transaction->vat as $line) {
    echo '  vat    ', $line->dim1->code, ' ', $line->debitCredit->value, ' ', $line->value, PHP_EOL;
}

// Clean up: delete the provisional example transaction again.
$purchases->delete($transaction->dayBook(), $transaction->number(), 'twinfield-php example');

echo 'Deleted:  ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
