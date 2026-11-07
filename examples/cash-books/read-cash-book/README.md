# Read cash book

Reads one cash book: the code from the command line, or else the first cash book.

`get()` returns a `CashBook`, or throws a `CashBookNotFoundException` when the code does not exist. Use `find()` to get `null` instead. Bank books are not cash books: `get('BNK')` throws as well (use `bankBooks()` for those).

- `shortName`: `null` when not set.
- `generalLedgerAccount`: the balance sheet account of the cash; `null` when not set, as on the default cash book `KAS` of a new company.

To book on the cash book, use its code with `cashTransactions()`: see [Daily cash register](../../cash-transactions/daily-cash-register/).

## Code

```php
$twinfield = Example::twinfield();

// The code from the command line, or else the first cash book.
$code = $argv[1] ?? $twinfield->cashBooks()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no cash books.' . PHP_EOL);

try {
    $cashBook = $twinfield->cashBooks()->get($code);
} catch (CashBookNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo 'Code:            ', $cashBook->code, PHP_EOL;
echo 'Name:            ', $cashBook->name, PHP_EOL;
echo 'Short name:      ', $cashBook->shortName ?? '-', PHP_EOL;
echo 'Ledger account:  ', $cashBook->generalLedgerAccount ?? '-', PHP_EOL;
```

## Run

```sh
php examples/cash-books/read-cash-book/example.php KAS
```

```text
Code:            KAS
Name:            Kas
Short name:      Kas
Ledger account:  -
```
