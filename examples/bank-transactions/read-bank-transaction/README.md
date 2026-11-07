# Read bank transaction

Reads one bank transaction: the header, the total line on the bank account, the detail and VAT lines, and what each line is matched with.

`get($dayBook, $number)` returns a `BankTransaction`, or throws a `TransactionNotFoundException` when the transaction (or the day book) does not exist. Use `find()` to get `null` instead.

- `$transaction->header`: company, day book, number, status (`Provisional` or `Final`), period, date, currency, user and dates.
- `$transaction->startValue` and `closeValue`: the balance of the statement, as `Amount`.
- `$transaction->total`: the line on the bank account, with the total including VAT. Its `debitCredit` is `debit` when more money came in than went out.
- `$transaction->details`: what the money is for. `dim2` is the customer, supplier or cost centre, with the name Twinfield adds; `matchStatus` and `matches` say whether the invoice is paid.
- `$transaction->vat`: the VAT per VAT code.

Amounts are `Amount` objects: exact numeric strings such as `121.00`, which you can `echo` directly.

## Code

```php
$twinfield = Example::twinfield();

if (!isset($argv[1], $argv[2])) {
    echo 'Pass a bank book and transaction number, e.g. BNK 202600045.', PHP_EOL;

    exit(0);
}

try {
    $transaction = $twinfield->bankTransactions()->get($argv[1], (int) $argv[2]);
} catch (TransactionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$header = $transaction->header;

echo 'Transaction: ', $header->dayBook, ' ', $header->number, ' (', $header->status->name, ')', PHP_EOL;
echo 'Date:        ', $header->date->format('Y-m-d'), ', period ', $header->period, PHP_EOL;
echo 'Statement:   ', $transaction->statementNumber ?? '-', PHP_EOL;
echo 'Balance:     ', $transaction->startValue, ' → ', $transaction->closeValue, ' ', $header->currency, PHP_EOL;
echo 'Total:       ', $transaction->total->debitCredit->value, ' ', $transaction->total->amounts->value,
    ' on ', $transaction->total->account->code, PHP_EOL;

foreach ($transaction->details as $line) {
    echo sprintf(
        '  %-6s %-6s %-8s %10s  %-12s %s',
        $line->debitCredit->value,
        $line->account->code,
        $line->dim2->code ?? '',
        $line->amounts->value,
        $line->matchStatus->value,
        $line->dim2->name ?? $line->description ?? '',
    ), PHP_EOL;

    foreach ($line->matches as $set) {
        foreach ($set->lines as $match) {
            echo '         matched with ', $match->dayBook, ' ', $match->number, ' line ', $match->line,
                ': ', $match->matchValue, PHP_EOL;
        }
    }
}

foreach ($transaction->vat as $line) {
    echo sprintf(
        '  %-6s %-6s VAT %-4s %10s',
        $line->debitCredit->value,
        $line->account->code,
        $line->vatCode,
        $line->amounts->value,
    ), PHP_EOL;
}
```

## Run

```sh
php examples/bank-transactions/read-bank-transaction/example.php BNK 202600045
```

```text
Transaction: BNK 202600045 (Provisional)
Date:        2026-11-03, period 2026/11
Statement:   245
Balance:     974.01 → 1397.46 EUR
Total:       debit 423.45 on 1010
  credit 1300   1000         435.55  matched      Example customer
         matched with VRK 202600012 line 1: 435.55
  debit  4500   KPL01         10.00  notmatchable Algemeen
  debit  1520   VAT IH         2.10
```
