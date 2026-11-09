# Customer transactions (100)

Browse code `100` (`BrowseCode::CustomerTransactions`, "Debiteurenkaart"): the transaction lines of customers, with the invoice number, due date, open amount and match status. Here: the open items, the invoices and payments that are not (fully) matched yet.

- `fin.trs.line.dim2` is the customer code, `fin.trs.line.dim2name` its name.
- `equal('fin.trs.line.matchstatus', 'available')`: lines that are not matched yet. Use `matched` for paid invoices, with `fin.trs.line.matchdate` (the payment date) and `fin.trs.line.matchnumber`.
- `fin.trs.line.openbasevaluesigned`: what is still open in the base currency; negative for a credit note or an unallocated payment.
- `between('fin.trs.line.dim2', $customer, $customer)`: one customer.
- `nullableDate('fin.trs.line.datedue')`: the due date can be empty, e.g. for a payment.

Criteria: period, day book, number, status, customer, amount, invoice number, match status, payment date and change date (`fin.trs.line.modified`). See [`browse-definitions/100.xml`](../../../browse-definitions/100.xml) and the [browse data guide](https://php-twinfield.solvebeam.com/guides/browse-data/). The open invoices are also available with the Finder: `salesTransactions()->openInvoices()`.

## Code

```php
$twinfield = Example::twinfield();

$customer = $argv[1] ?? null;
$year = Example::lastYear($twinfield);

// Open items: invoices and payments that are not (fully) matched yet.
$query = $twinfield->browse(BrowseCode::CustomerTransactions)
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

if ($customer !== null) {
    $query = $query->between('fin.trs.line.dim2', $customer, $customer);
}

$result = $query->get();

echo count($result), ' open item(s)', $customer === null ? '' : ' of customer ' . $customer, PHP_EOL;

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
php examples/browse-data/100-customer-transactions/example.php          # all customers
php examples/browse-data/100-customer-transactions/example.php 1000     # customer 1000
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
55 open item(s)
[1/55] 1002 Pixel & Co, MEMO 202200003 invoice - of 2022-10-19, due 2022-11-18: -100.00, open -100.00
[2/55] 1004 Familie Jansen, MEMO 202200003 invoice - of 2022-10-19, due 2022-11-18: 100.00, open 100.00
[3/55] 1000 Voorbeeld BV, VRK 201000001 invoice 5 of 2010-12-20, due 2011-01-03: 148.75, open 148.75
[4/55] 1000 Voorbeeld BV, VRK 201000002 invoice 6 of 2010-12-28, due 2011-01-11: 148.75, open 148.75
…
Total open: 36459.13
```
