<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\SalesTransaction\SalesTransactionInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$sales = $twinfield->salesTransactions();

// In your own application: your sales day book, customer, revenue account and VAT code.
$customer = Example::customerCodes($twinfield, 1)[0];
$revenue = Example::profitAndLossAccount($twinfield);
$vatCode = $twinfield->vatCodes()->get(Example::salesVatCode($twinfield));
$date = new DateTimeImmutable('today');

// The invoice was made in your own software: book its amounts. With a VatCode, the VAT is
// calculated with its percentage on the invoice date, so the total line matches Twinfield.
$input = SalesTransactionInput::invoice('VRK', $customer, 'EXAMPLE-' . $date->format('Ymd'), $date)
    ->dueDate($date->modify('+30 days'))
    ->revenue($revenue, '100.00', vatCode: $vatCode, description: 'Example sale');

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

echo 'Created:  ', $transaction->dayBook(), ' ', $transaction->number(), ' (', $transaction->header->status->name, ')', PHP_EOL;
echo 'Invoice:  ', $transaction->invoiceNumber, ', due ', $transaction->dueDate->format('Y-m-d'), PHP_EOL;
echo 'Customer: ', $transaction->customer()->code, ' ', $transaction->customer()->name, PHP_EOL;
echo 'Total:    ', $transaction->value(), ' including ', $transaction->total->vatTotal, ' VAT, open ',
    $transaction->total->openBaseValue, PHP_EOL;

foreach ($transaction->details as $line) {
    echo '  detail ', $line->account->code, ' ', $line->debitCredit->value, ' ', $line->value,
        ' ', $line->vatCode, ' ', $line->vatValue, PHP_EOL;
}

foreach ($transaction->vat as $line) {
    echo '  vat    ', $line->account->code, ' ', $line->debitCredit->value, ' ', $line->value, PHP_EOL;
}

// Clean up: delete the provisional example transaction again.
$sales->delete($transaction->dayBook(), $transaction->number(), 'php-twinfield example');

echo 'Deleted:  ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
