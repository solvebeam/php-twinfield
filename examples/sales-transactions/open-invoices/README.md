# Open invoices

Lists open (unpaid) invoices with Finder type `IVT`, optionally of one customer. Read-only.

`openInvoices()` returns an `OpenInvoiceSearch`. Filter with `customer()` (Finder option `dim2`), `account()` (the accounts receivable account, `dim1`), `value()` (the original amount) or `openValue()` (the outstanding amount), and search with `pattern()` on customer code, name, amount and invoice number. `get()` gives one page, `all()` every result page by page.

Each `OpenInvoice` has the invoice number, the amount, the customer code and name, and a side. The Finder gives no day book or transaction number.

Two things the Twinfield documentation does not say:

- Without `customer()` or `account()`, the results also include open **purchase** invoices, with the supplier in the customer column.
- The side is the opposite of the total line of the invoice: `C` (credit) for a sales invoice, `D` (debit) for a purchase invoice or a sales credit note.

## Code

```php
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
```

## Run

```sh
php examples/sales-transactions/open-invoices/example.php 1000
```

```text
2 open invoice(s) of 1000
  2026-0042            181.50 C  1000   Example customer
  2026-0043            121.00 D  1000   Example customer
```
