<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceNotFoundException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

if (!isset($argv[1], $argv[2])) {
    echo 'Pass an invoice type and invoice number, e.g. FACTUUR 5.', PHP_EOL;

    exit(0);
}

$invoices = $twinfield->salesInvoices();

try {
    $invoice = $invoices->get($argv[1], (int) $argv[2]);
} catch (SalesInvoiceNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo 'Invoice:  ', $invoice->invoiceType, ' ', $invoice->invoiceNumber, ' (', $invoice->status->value, ')', PHP_EOL;
echo 'Customer: ', $invoice->customer, ', ', $invoice->paymentMethod->value, ' via ', $invoice->bank, PHP_EOL;
echo 'Date:     ', $invoice->invoiceDate->format('Y-m-d'), ', due ', $invoice->dueDate->format('Y-m-d'),
    ', period ', $invoice->period, PHP_EOL;

foreach ($invoice->lines as $line) {
    echo sprintf(
        '  %s x %-6s %-30s %10s  %-4s %8s  on %s',
        $line->quantity,
        $line->article,
        $line->description,
        $line->valueExcl,
        $line->vatCode,
        $line->vatValue,
        $line->dim1->code,
    ), PHP_EOL;
}

foreach ($invoice->vatLines as $line) {
    echo '  VAT ', $line->vatCode, ': ', $line->vatValue, PHP_EOL;
}

echo 'Total:    ', $invoice->totals->valueExcl, ' + ', $invoice->totals->vat(), ' VAT = ',
    $invoice->totals->valueInc, ' ', $invoice->currency, PHP_EOL;

// A final invoice is booked as a sales transaction: read it, e.g. to see whether it is paid.
if ($invoice->transaction !== null) {
    $transaction = $invoices->salesTransaction($invoice);

    echo 'Booked:   ', $transaction->dayBook(), ' ', $transaction->number(), ', ',
        $transaction->total->matchStatus->value, ', open ', $transaction->total->openBaseValue ?? '-', PHP_EOL;
}
