<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$invoices = $twinfield->salesInvoices();

if (!isset($argv[1], $argv[2])) {
    echo 'Pass the invoice type and number of a concept invoice, e.g. FACTUUR 12, and --save.', PHP_EOL;

    exit(0);
}

$invoice = $invoices->get($argv[1], (int) $argv[2]);

if ($invoice->isFinal()) {
    echo 'Invoice ', $invoice->invoiceType, ' ', $invoice->invoiceNumber, ' is already final.', PHP_EOL;

    exit(0);
}

if (!in_array('--save', $argv, true)) {
    echo 'Dry run; add --save to make concept invoice ', $invoice->invoiceType, ' ', $invoice->invoiceNumber,
        ' (', $invoice->totals->valueInc, ') final. This can not be undone.', PHP_EOL;

    exit(0);
}

try {
    // Final: no changes anymore, and Twinfield books the sales transaction.
    $invoice = $invoices->finalize($invoice->invoiceType, $invoice->invoiceNumber);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

$transaction = $invoices->salesTransaction($invoice);

echo 'Final:    ', $invoice->invoiceType, ' ', $invoice->invoiceNumber, PHP_EOL;
echo 'Booked:   ', $transaction->dayBook(), ' ', $transaction->number(), ', ', $transaction->value(),
    ', due ', $transaction->dueDate->format('Y-m-d'), PHP_EOL;
