# List invoice types

Lists the invoice types of the company, with Finder type `INV`. A sales invoice always has an invoice type: it decides the numbering, whether prices include VAT, and the account for the one-off item `0`.

`salesInvoices()->invoiceTypes()` returns a `CodeNameSearch`: `get()` gives one page, `all()` every invoice type. Each item is an `InvoiceTypeSummary` with `code`, `name` and the `company`.

Pass a pattern to search, e.g. `F*`.

## Code

```php
$twinfield = Example::twinfield();

// Optional search pattern from the command line, e.g. `F*`.
$pattern = $argv[1] ?? '*';

foreach ($twinfield->salesInvoices()->invoiceTypes($pattern)->all() as $type) {
    echo $type->code, '  ', $type->name, PHP_EOL;
}
```

## Run

```sh
php examples/sales-invoices/list-invoice-types/example.php
```

```text
FACTUUR  Factuur
UREN  Uren factuur
```
