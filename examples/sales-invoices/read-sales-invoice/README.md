# Read sales invoice

Reads one sales invoice of the Classic invoicing module: the header, the lines with the amounts Twinfield calculated, the VAT and the totals. For a final invoice, it also reads the sales transaction Twinfield booked.

`get($invoiceType, $invoiceNumber)` returns a `SalesInvoice`, or throws a `SalesInvoiceNotFoundException` when the invoice (or the invoice type) does not exist. Use `find()` to get `null` instead.

- `$invoice->status`: `concept` (can still be changed) or `final` (booked).
- `$invoice->lines`: per line the article, quantity, price per unit (`unitPriceExcl` or `unitPriceInc`, depending on the invoice type), VAT code, the account Twinfield books it on, and the calculated `valueExcl`, `vatValue` and `valueInc`.
- `$invoice->vatLines`: the VAT per VAT code.
- `$invoice->totals`: `valueExcl`, `valueInc` and `vat()`.
- `$invoice->transaction`: day book and number of the sales transaction; only for a final invoice. `salesInvoices()->salesTransaction($invoice)` reads it, e.g. to see whether the invoice is paid.

There is no search for sales invoices: you need the invoice type and number. To find unpaid invoices, see [Open invoices](../../sales-transactions/open-invoices/).

## Code

```php
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
        $line->account->code,
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
```

## Run

```sh
php examples/sales-invoices/read-sales-invoice/example.php FACTUUR 5
```

```text
Invoice:  FACTUUR 5 (final)
Customer: 1000, cash via BNK
Date:     2026-11-03, due 2026-12-03, period 2026/11
  1 x 1      Consultancy                        100.00  VH      21.00  on 8000
  1 x 2      Training                            50.00  VH      10.50  on 8000
  VAT VH: 31.50
Total:    150.00 + 31.50 VAT = 181.50 EUR
Booked:   VRK 202600012, available, open 181.50
```
