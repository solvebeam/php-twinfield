# Read purchase transaction

Reads one purchase transaction: the invoice number of the supplier and the due date, the total line with the supplier, the cost and VAT lines, whether it is paid, and with which payments.

`get($dayBook, $number)` returns a `PurchaseTransaction`, or throws a `TransactionNotFoundException` when the transaction (or the day book) does not exist. Use `find()` to get `null` instead. The purchase day book is usually `INK` in Dutch companies and `PNV` in English ones.

- `$transaction->header`: company, day book, number, status (`Provisional` or `Final`), period, date, currency, origin, user and dates.
- `$transaction->invoiceNumber`, `dueDate` and `paymentReference`. `dueDate` is null for a purchase invoice entered by hand without due date; `paymentReference` is null when not set.
- `$transaction->total`: the line with the supplier (`dim2`) and the accounts payable account (`account`), the total including VAT, the VAT total, the payment status (`matchStatus`), the open amount and the payments it is matched with (`matches`).
- `$transaction->details`: the costs without VAT, with VAT code and VAT amount, and the cost center (`dim2`) and project (`dim3`).
- `$transaction->vat`: the VAT per VAT code, with the amount it was calculated on (`vatTurnover`).

`value()` is the total signed: positive for an invoice (what you owe the supplier), negative for a credit note (`isCreditNote()`). `isPaid()` is true when the invoice is matched with payments.

Twinfield gives the open amount in the base currency (`openBaseValue`) **signed**: negative for a purchase invoice, because its total line is credit. Imported invoices can have discount lines with a negative amount on the debit side; the SDK keeps them as they are, also in `edit()`.

The Finder gives no transaction numbers; see [Open invoices](../open-invoices/) to find unpaid invoices by supplier.

## Code

```php
$twinfield = Example::twinfield();

if (!isset($argv[1], $argv[2])) {
    echo 'Pass a purchase day book and transaction number, e.g. INK 202600021.', PHP_EOL;

    exit(0);
}

try {
    $transaction = $twinfield->purchaseTransactions()->get($argv[1], (int) $argv[2]);
} catch (TransactionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$header = $transaction->header;
$total = $transaction->total;

echo 'Transaction: ', $header->dayBook, ' ', $header->number, ' (', $header->status->name, ')', PHP_EOL;
echo 'Invoice:     ', $transaction->invoiceNumber, $transaction->isCreditNote() ? ' (credit note)' : '', PHP_EOL;
echo 'Date:        ', $header->date->format('Y-m-d'), ', due ', $transaction->dueDate?->format('Y-m-d') ?? '-',
    ', period ', $header->period, PHP_EOL;
echo 'Supplier:    ', $transaction->supplier()->code, ' ', $transaction->supplier()->name, ' on ',
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
php examples/purchase-transactions/read-purchase-transaction/example.php INK 202600012
```

```text
Transaction: INK 202600012 (Final)
Invoice:     F-2026-0042
Date:        2026-11-03, due 2026-12-03, period 2026/11
Supplier:    2000 Example supplier on 1600
Total:       181.50 EUR, VAT 31.50
Payment:     available, open -181.50
  debit  7000       100.00  IH      21.00  Consultancy
  debit  7000        50.00  IH      10.50  Training
  debit  1520        31.50  VAT IH on 150.00
```
