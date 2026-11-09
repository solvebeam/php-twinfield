# Read reversal

Reads the reversal of a journal transaction: the reversed transaction Twinfield made for it, if any, and the reversal settings.

`get($dayBook, $number)` returns a `Reversal`:

- `$reversal->original`: the journal transaction you asked for (company, day book and number).
- `$reversal->reversed`: the reversed transaction, a complete reverse of the original in the reversal day book (e.g. `MEMOTEGENBOEKING`), or `null` when there is none. Read it with `journalTransactions()->get()`.
- `$reversal->reverseInNextPeriod`: the reversed transaction is posted in the period after the original.

Only journal transactions have a reversal. For any other transaction, and for a number that does not exist, Twinfield answers with an error (`Originele transactie VRK:202600012 is niet gevonden.`), thrown as `ProcessXmlException`. A deleted journal transaction is still found, without reversed transaction.

## Code

```php
$twinfield = Example::twinfield();

// E.g. `MEMO 202600003`: day book and number of a journal transaction.
[, $dayBook, $number] = array_pad($argv ?? [], 3, null);

if ($dayBook === null || $number === null) {
    echo 'Usage: php example.php <day book> <transaction number>', PHP_EOL;

    exit(0);
}

try {
    $reversal = $twinfield->reversalTransactions()->get($dayBook, (int) $number);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Original:               ', $reversal->original->dayBook, ' ', $reversal->original->number, PHP_EOL;
echo 'Reversed:               ', $reversal->reversed === null
    ? '-'
    : $reversal->reversed->dayBook . ' ' . $reversal->reversed->number, PHP_EOL;
echo 'Reverse in next period: ', $reversal->reverseInNextPeriod ? 'yes' : 'no', PHP_EOL;
```

## Run

```sh
php examples/reversal-transactions/read-reversal/example.php MEMO 202600003
```

```text
Original:               MEMO 202600003
Reversed:               -
Reverse in next period: no
```
