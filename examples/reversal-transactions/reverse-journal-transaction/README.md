# Reverse journal transaction

Reverses a final journal transaction: Twinfield creates the reversed transaction, posts it as final and links it to the original.

> **This example changes data in Twinfield.** Only run it on a test administration. The original and the reversed transaction are both final: they cannot be changed or deleted.

`postFinal($dayBook, $number)` returns the reversed transaction (a `TransactionReference`), in the reversal day book of the journal, e.g. `MEMOTEGENBOEKING`. The example first checks with `get()` that the transaction is not reversed yet, and then reads the reversed transaction with `journalTransactions()->get()`.

For a provisional journal transaction, `postProvisional()` only saves the reversal settings, and `undo()` removes them again.

## Code

```php
$twinfield = Example::twinfield();

// E.g. `MEMO 202600003`: day book and number of a final journal transaction.
[, $dayBook, $number] = array_pad($argv ?? [], 3, null);

if ($dayBook === null || $number === null) {
    echo 'Usage: php example.php <day book> <transaction number>', PHP_EOL;

    exit(0);
}

$reversals = $twinfield->reversalTransactions();

try {
    $existing = $reversals->get($dayBook, (int) $number);

    if ($existing->reversed !== null) {
        echo 'Already reversed by ', $existing->reversed->dayBook, ' ', $existing->reversed->number, PHP_EOL;

        exit(0);
    }

    $reversed = $reversals->postFinal($dayBook, (int) $number);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Reversed ', $dayBook, ' ', $number, ' by ', $reversed->dayBook, ' ', $reversed->number, PHP_EOL;

$transaction = $twinfield->journalTransactions()->get($reversed->dayBook, $reversed->number);

echo 'Debit ', $transaction->debit(), ', credit ', $transaction->credit(), PHP_EOL;
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/reversal-transactions/reverse-journal-transaction/example.php MEMO 202600003
```

The output below follows the documentation; it was not run live yet.

```text
Reversed MEMO 202600003 by MEMOTEGENBOEKING 202600001
Debit 435.55, credit 435.55
```
