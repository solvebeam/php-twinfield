# Finalize sales invoice

Makes a concept sales invoice final. Without `--save` it only shows which invoice. With `--save` it changes data in Twinfield, and this can't be undone: a final invoice can't be changed anymore, and Twinfield books the sales transaction in the general ledger.

`finalize($invoiceType, $invoiceNumber)` reads the invoice and saves it with status `final`; it throws a `LogicException` when the invoice is already final. The result has the booked transaction in `$invoice->transaction`, and `salesTransaction($invoice)` reads it.

From the sales transaction on, you can match a payment against it, see it in the open items and send reminders: see [Sales invoice or sales transaction?](https://php-twinfield.solvebeam.com/knowledge-base/sales-invoice-or-sales-transaction/).

To make an invoice final when you create it, call `final()` on the `SalesInvoiceInput`.

## Code

```php
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
```

## Run

```sh
php examples/sales-invoices/finalize-sales-invoice/example.php FACTUUR 12
```

```text
Dry run; add --save to make concept invoice FACTUUR 12 (151.25) final. This can not be undone.
```

Add `--save` to make it final:

```sh
php examples/sales-invoices/finalize-sales-invoice/example.php FACTUUR 12 --save
```
