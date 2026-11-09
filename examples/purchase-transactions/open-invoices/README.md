# Open invoices

Lists open (unpaid) purchase invoices with Finder type `IVT`, optionally of one supplier. Read-only.

`openInvoices()` returns an `OpenInvoiceSearch`, the same search as for sales transactions. Filter with `supplier()` (Finder option `dim2`), `account()` (the accounts payable account, `dim1`), `value()` (the original amount) or `openValue()` (the outstanding amount), and search with `pattern()` on supplier code, name, amount and invoice number. `get()` gives one page, `all()` every result page by page.

Each `OpenInvoice` has the invoice number, the open amount, the supplier code and name, and a side. The Finder gives no day book or transaction number.

Things the Twinfield documentation does not say:

- Without `supplier()` or `account()`, the results also include open **sales** invoices.
- The side is the opposite of the total line of the invoice: `D` (debit) for a purchase invoice, `C` (credit) for a purchase credit note or a sales invoice.
- The amount is the open amount, always positive: for a partly paid invoice of 15.00 with 10.00 still open, the Finder gives 10.00.

## Code

```php
$twinfield = Example::twinfield();

// Without a supplier, the Finder also gives open sales invoices.
$search = $twinfield->purchaseTransactions()->openInvoices();

if (isset($argv[1])) {
    $search = $search->supplier($argv[1]);
}

$page = $search->limit(20)->get();

echo $page->totalRows, ' open invoice(s)', isset($argv[1]) ? ' of ' . $argv[1] : '', PHP_EOL;

foreach ($page as $invoice) {
    echo sprintf(
        '  %-16s %10s %s  %-6s %s',
        $invoice->invoiceNumber,
        $invoice->amount,
        // The Finder gives the side opposite to the invoice: debit for a purchase invoice.
        $invoice->debitCredit === DebitCredit::Debit ? 'D' : 'C',
        $invoice->relation,
        $invoice->relationName,
    ), PHP_EOL;
}
```

## Run

```sh
php examples/purchase-transactions/open-invoices/example.php 2000
```

```text
2 open invoice(s) of 2000
  F-2026-0042          181.50 D  2000   Example supplier
  C-2026-0007           48.40 C  2000   Example supplier
```
