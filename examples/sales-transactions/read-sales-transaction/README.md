# Read sales transaction

Reads one sales transaction: the invoice number and due date, the total line with the customer, the revenue and VAT lines, whether it is paid, and with which payments.

`get($dayBook, $number)` returns a `SalesTransaction`, or throws a `TransactionNotFoundException` when the transaction (or the day book) does not exist. Use `find()` to get `null` instead.

- `$transaction->header`: company, day book, number, status (`Provisional` or `Final`), period, date, currency, origin, user and dates.
- `$transaction->invoiceNumber`, `dueDate` and `paymentReference` (null when not set).
- `$transaction->total`: the line with the customer (`customer`) and the accounts receivable account (`account`), the total including VAT, the VAT total, the payment status (`matchStatus`), the open amount and the payments it is matched with (`matches`).
- `$transaction->details`: the revenue without VAT, with VAT code and VAT amount, and the cost center (`dim2`) and project (`dim3`).
- `$transaction->vat`: the VAT per VAT code, with the amount it was calculated on (`vatTurnover`).

`value()` is the total signed: positive for an invoice, negative for a credit note (`isCreditNote()`). `isPaid()` is true when the invoice is matched with payments. Twinfield gives the open amount in the base currency (`openBaseValue`). Amounts are `Amount` objects: exact numeric strings such as `121.00`, which you can `echo` directly.

The Finder gives no transaction numbers; see [Open invoices](../open-invoices/) to find unpaid invoices by customer.

## Code

```php
$twinfield = Example::twinfield();

if (!isset($argv[1], $argv[2])) {
    echo 'Pass a sales day book and transaction number, e.g. VRK 202600012.', PHP_EOL;

    exit(0);
}

try {
    $transaction = $twinfield->salesTransactions()->get($argv[1], (int) $argv[2]);
} catch (TransactionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$header = $transaction->header;
$total = $transaction->total;

echo 'Transaction: ', $header->dayBook, ' ', $header->number, ' (', $header->status->name, ')', PHP_EOL;
echo 'Invoice:     ', $transaction->invoiceNumber, $transaction->isCreditNote() ? ' (credit note)' : '', PHP_EOL;
echo 'Date:        ', $header->date->format('Y-m-d'), ', due ', $transaction->dueDate->format('Y-m-d'),
    ', period ', $header->period, PHP_EOL;
echo 'Customer:    ', $transaction->customer()->code, ' ', $transaction->customer()->name, ' on ',
    $total->account->code, PHP_EOL;
echo 'Total:       ', $transaction->value(), ' ', $header->currency, ', VAT ', $total->vatTotal ?? '-', PHP_EOL;
echo 'Payment:     ', $total->matchStatus->value, ', open ', $total->openBaseValue ?? '-', PHP_EOL;

foreach ($transaction->details as $line) {
    echo sprintf(
        '  %-6s %-6s %10s  %-4s %8s  %s',
        $line->debitCredit->value,
        $line->account->code,
        $line->amounts->value,
        $line->vatCode ?? '',
        $line->vatValue ?? '',
        $line->description ?? '',
    ), PHP_EOL;
}

foreach ($transaction->vat as $line) {
    echo sprintf(
        '  %-6s %-6s %10s  VAT %s on %s',
        $line->debitCredit->value,
        $line->account->code,
        $line->amounts->value,
        $line->vatCode,
        $line->vatTurnover ?? '-',
    ), PHP_EOL;
}

foreach ($total->matches as $set) {
    foreach ($set->lines as $match) {
        echo '  paid with ', $match->dayBook, ' ', $match->number, ' line ', $match->line, ': ', $match->matchValue,
            ' on ', $set->matchDate->format('Y-m-d'), PHP_EOL;
    }
}
```

## Run

```sh
php examples/sales-transactions/read-sales-transaction/example.php VRK 202600012
```

```text
Transaction: VRK 202600012 (Final)
Invoice:     2026-0042
Date:        2026-11-03, due 2026-12-03, period 2026/11
Customer:    1000 Example customer on 1300
Total:       181.50 EUR, VAT 31.50
Payment:     available, open 181.50
  credit 8000       100.00  VH      21.00  Consultancy
  credit 8000        50.00  VH      10.50  Training
  credit 1530        31.50  VAT VH on 150.00
```
