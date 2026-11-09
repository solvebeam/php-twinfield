# Supplier transactions (200)

Browse code `200` (`BrowseCode::SupplierTransactions`, "Crediteurenkaart"): the transaction lines of suppliers, with the invoice number, due date, open amount and match status. Here: the open items, the purchase invoices and payments that are not (fully) matched yet. It has the same fields as [100](../100-customer-transactions/) for customers.

- `fin.trs.line.dim2` is the supplier code, `fin.trs.line.dim2name` its name.
- `equal('fin.trs.line.matchstatus', 'available')`: lines that are not matched yet. Use `matched` for paid invoices.
- `fin.trs.line.openbasevaluesigned`: what is still open in the base currency; negative for an invoice you still have to pay.
- `between('fin.trs.line.dim2', $supplier, $supplier)`: one supplier.

Criteria: period, day book, number, status, supplier, amount, invoice number, match status, payment date and change date. See [`browse-definitions/200.xml`](../../../browse-definitions/200.xml) and the [browse data guide](https://php-twinfield.solvebeam.com/guides/browse-data/). The open invoices are also available with the Finder: `purchaseTransactions()->openInvoices()`.

## Code

```php
$twinfield = Example::twinfield();

$supplier = $argv[1] ?? null;
$year = Example::lastYear($twinfield);

// Open items: invoices and payments that are not (fully) matched yet.
$query = $twinfield->browse(BrowseCode::SupplierTransactions)
    ->columns(
        'fin.trs.line.dim2',
        'fin.trs.line.dim2name',
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.invnumber',
        'fin.trs.head.date',
        'fin.trs.line.datedue',
        'fin.trs.line.basevaluesigned',
        'fin.trs.line.openbasevaluesigned',
    )
    ->between('fin.trs.head.yearperiod', '2000/00', sprintf('%d/55', $year))
    ->equal('fin.trs.head.status', 'normal')
    ->equal('fin.trs.line.matchstatus', 'available');

if ($supplier !== null) {
    $query = $query->between('fin.trs.line.dim2', $supplier, $supplier);
}

$result = $query->get();

echo count($result), ' open item(s)', $supplier === null ? '' : ' of supplier ' . $supplier, PHP_EOL;

$open = Amount::zero();

foreach ($result as $index => $row) {
    $due = $row->nullableDate('fin.trs.line.datedue');
    $open = $open->plus($row->amount('fin.trs.line.openbasevaluesigned'));

    echo sprintf(
        '[%d/%d] %s %s, %s %d invoice %s of %s, due %s: %s, open %s',
        $index + 1,
        count($result),
        $row->string('fin.trs.line.dim2'),
        $row->nullableString('fin.trs.line.dim2name') ?? '',
        $row->string('fin.trs.head.code'),
        $row->int('fin.trs.head.number'),
        $row->nullableString('fin.trs.line.invnumber') ?? '-',
        $row->date('fin.trs.head.date')->format('Y-m-d'),
        $due === null ? '-' : $due->format('Y-m-d'),
        $row->amount('fin.trs.line.basevaluesigned'),
        $row->amount('fin.trs.line.openbasevaluesigned'),
    ), PHP_EOL;
}

echo 'Total open: ', $open, PHP_EOL;
```

## Run

```sh
php examples/browse-data/200-supplier-transactions/example.php          # all suppliers
php examples/browse-data/200-supplier-transactions/example.php 2001     # supplier 2001
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
11 open item(s)
[1/11] 2001 Leverancier A, INK 201600001 invoice 816700871008 of 2016-08-08, due 2016-09-05: -15.00, open -10.00
[2/11] 2001 Leverancier A, INK 201600002 invoice 816700871009 of 2016-08-08, due 2016-09-05: -20.00, open -20.00
[3/11] 2001 Leverancier A, INK 201800001 invoice 123 of 2018-02-01, due 2018-02-24: -6050.00, open -6050.00
[4/11] 2003 Brandstof BV, INK 202000001 invoice 20200124 of 2020-01-24, due 2020-01-24: -10.00, open -10.00
…
Total open: -18906.82
```
