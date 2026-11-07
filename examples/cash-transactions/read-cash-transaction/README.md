# Read cash transaction

Reads one cash transaction. Cash transactions work like [bank transactions](../../bank-transactions/read-bank-transaction/): one statement, with a total line on the cash account and detail and VAT lines.

`get($dayBook, $number)` returns a `CashTransaction`, or throws a `TransactionNotFoundException` when it does not exist.

## Code

```php
$twinfield = Example::twinfield();

if (!isset($argv[1], $argv[2])) {
    echo 'Pass a cash book and transaction number, e.g. KAS 202600046.', PHP_EOL;

    exit(0);
}

try {
    $transaction = $twinfield->cashTransactions()->get($argv[1], (int) $argv[2]);
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
php examples/cash-transactions/read-cash-transaction/example.php KAS 202600046
```

```text
Transaction: KAS 202600046 (Provisional)
Date:        2026-11-03, period 2026/11
Statement:   246
Balance:     1397.46 → 1518.46 EUR
Total:       debit 121.00 on 1000
  credit 1300   1000         121.00  available    Example customer
```
