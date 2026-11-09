<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\SalesInvoice\PaymentMethod;
use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$invoices = $twinfield->salesInvoices();

// In your own application: your invoice type, customer and VAT code.
$invoiceType = 'FACTUUR';
$customer = Example::customerCodes($twinfield, 1)[0];
$vatCode = Example::salesVatCode($twinfield);
$date = new DateTimeImmutable('today');

// Twinfield makes the invoice: it gives the number and calculates the amounts and VAT. The
// one-off item `0` takes your own description and price; other articles their own.
$input = SalesInvoiceInput::create($invoiceType, $customer)
    ->invoiceDate($date)
    ->dueDate($date->modify('+30 days'))
    ->paymentMethod(PaymentMethod::Bank)
    ->oneOff('Example sale', '100.00', vatCode: $vatCode);

$mode = $argv[1] ?? '';

if ($mode !== '--calculate' && $mode !== '--save') {
    echo 'Dry run; add --calculate to let Twinfield calculate it without saving, or --save to create it',
        ' as concept invoice (it is not deleted again):', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml((string) $twinfield->companyCode())), PHP_EOL;

    exit(0);
}

try {
    if ($mode === '--calculate') {
        $calculation = $invoices->calculate($input);

        echo 'Calculated: ', $calculation->totals->valueExcl, ' + ', $calculation->totals->vat(), ' VAT = ',
            $calculation->totals->valueInc, ' (not saved)', PHP_EOL;

        exit(0);
    }

    $invoice = $invoices->create($input);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

echo 'Created:  ', $invoice->invoiceType, ' ', $invoice->invoiceNumber, ' (', $invoice->status->value, ')', PHP_EOL;
echo 'Total:    ', $invoice->totals->valueExcl, ' + ', $invoice->totals->vat(), ' VAT = ',
    $invoice->totals->valueInc, ' ', $invoice->currency, PHP_EOL;

foreach ($invoice->lines as $line) {
    echo '  ', $line->quantity, ' x ', $line->description, ' ', $line->valueExcl, ' ', $line->vatCode,
        ' on ', $line->account->code, PHP_EOL;
}
