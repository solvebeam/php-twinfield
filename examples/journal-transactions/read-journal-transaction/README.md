# Read journal transaction

Reads one journal transaction: the header with the regime, and the detail and VAT lines with their own side.

`get($dayBook, $number)` returns a `JournalTransaction`, or throws a `TransactionNotFoundException` when the transaction (or the day book) does not exist or was deleted (`$exception->deleted`). Use `find()` to get `null` instead.

- `$transaction->header`: company, day book, number, status (`Provisional` or `Final`), period, date, currency, origin, user and dates.
- `$transaction->regime`: `Generic` (visible in all regimes), `Fiscal`, `Commercial` or `Economic`.
- `$transaction->details`: the lines with account (`dim1`), customer, supplier or cost center (`dim2`), project (`dim3`), side, amount, and VAT code and amount.
- `$transaction->vat`: the VAT Twinfield booked for the detail lines with a VAT code, with the amount it was calculated on (`vatTurnover`).

A journal transaction has no total line: `debit()` and `credit()` are the sums of both sides, and they are always equal. Amounts are `Amount` objects: exact numeric strings such as `435.55`, which you can `echo` directly.

There is no search that gives transaction numbers: pass a day book and number you know, e.g. from the Twinfield user interface.

## Code

```php
$twinfield = Example::twinfield();

if (!isset($argv[1], $argv[2])) {
    echo 'Pass a journal day book and transaction number, e.g. MEMO 202600003.', PHP_EOL;

    exit(0);
}

try {
    $transaction = $twinfield->journalTransactions()->get($argv[1], (int) $argv[2]);
} catch (TransactionNotFoundException $exception) {
    echo $exception->deleted ? 'Deleted' : 'Not found: ' . $exception->reason, PHP_EOL;

    exit(1);
}

$header = $transaction->header;

echo 'Transaction: ', $header->dayBook, ' ', $header->number, ' (', $header->status->name, ')', PHP_EOL;
echo 'Date:        ', $header->date->format('Y-m-d'), ', period ', $header->period, ', regime ',
    $transaction->regime->value, PHP_EOL;
echo 'Total:       ', $transaction->debit(), ' debit = ', $transaction->credit(), ' credit ', $header->currency,
    PHP_EOL;

foreach ($transaction->details as $line) {
    echo sprintf(
        '  %-6s %-6s %-6s %10s  %-7s %8s  %s',
        $line->debitCredit->value,
        $line->account->code,
        $line->dim2->code ?? '',
        $line->amounts->value,
        $line->vatCode ?? '',
        $line->vatValue ?? '',
        $line->description ?? '',
    ), PHP_EOL;
}

foreach ($transaction->vat as $line) {
    echo sprintf(
        '  %-6s %-6s %-6s %10s  VAT %s on %s',
        $line->debitCredit->value,
        $line->account->code,
        '',
        $line->amounts->value,
        $line->vatCode,
        $line->vatTurnover ?? '-',
    ), PHP_EOL;
}
```

## Run

```sh
php examples/journal-transactions/read-journal-transaction/example.php MEMO 202600003
```

```text
Transaction: MEMO 202600003 (Provisional)
Date:        2026-11-30, period 2026/11, regime Generic
Total:       1210.00 debit = 1210.00 credit EUR
  credit 8199             1000.00  PRIVE-H   210.00  Private use car
  debit  8199             1000.00                    Private use car
  debit  4406   00100      210.00
  credit 1618              210.00  VAT PRIVE-H on 1000.00
```
