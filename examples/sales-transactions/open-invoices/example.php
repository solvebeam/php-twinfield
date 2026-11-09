<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\DebitCredit;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Without a customer, the Finder also gives open purchase invoices.
$search = $twinfield->salesTransactions()->openInvoices();

if (isset($argv[1])) {
    $search = $search->customer($argv[1]);
}

$page = $search->limit(20)->get();

echo $page->totalRows, ' open invoice(s)', isset($argv[1]) ? ' of ' . $argv[1] : '', PHP_EOL;

foreach ($page as $invoice) {
    echo sprintf(
        '  %-16s %10s %s  %-6s %s',
        $invoice->invoiceNumber,
        $invoice->amount,
        // The Finder gives the side opposite to the invoice: credit for a sales invoice.
        $invoice->debitCredit === DebitCredit::Credit ? 'C' : 'D',
        $invoice->relation,
        $invoice->relationName,
    ), PHP_EOL;
}
