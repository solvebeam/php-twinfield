# Update cash book

Changes the name and short name of a cash book, then restores them. This changes data in Twinfield. It uses the cash book `EXAMPLE` from [Create cash book](../create-cash-book/), or the code from the command line.

The CashBookService has no single update command, only one command per value: `ChangeCashBookName`, `ChangeCashBookShortName` and `ChangeCashBookGeneralLedgerAccount`. `update()` reads the cash book, compares it with the input and sends only the commands for values that differ. Nothing changed means no command, and an unchanged ledger account never runs into "may not be changed" once the cash book is in use.

The commands are separate requests: when one fails, the ones before it are already done. To send one command yourself, use `rename()`, `changeShortName()` or `changeGeneralLedgerAccount()`.

## Code

```php
$twinfield = Example::twinfield();

$cashBooks = $twinfield->cashBooks();
$original = $cashBooks->get($argv[1] ?? 'EXAMPLE');

echo 'Before: ', $original->name, ', short name ', $original->shortName ?? '-', PHP_EOL;

try {
    $cashBook = $cashBooks->update(
        $original->edit()
            ->name($original->name . ' (updated)')
            ->shortName(null),
    );

    echo 'After:  ', $cashBook->name, ', short name ', $cashBook->shortName ?? '-', PHP_EOL;
} finally {
    $cashBooks->update($original->edit());
}
```

## Run

```sh
php examples/cash-books/update-cash-book/example.php
```

```text
Before: Example cash, short name Example
After:  Example cash (updated), short name -
```
