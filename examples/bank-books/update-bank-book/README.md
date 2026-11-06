# Update bank book

Changes the name and bank account of a bank book, then restores them. This changes data in Twinfield. It uses the bank book `EXAMPLE` from [Create bank book](../create-bank-book/), or the code from the command line.

The BankBookService has no single update command, only one command per value: `ChangeBankBookName`, `ChangeBankBookShortName`, `ChangeBankBookBankAccount` and `ChangeBankBookGeneralLedgerAccount`. `update()` reads the bank book, compares it with the input and sends only the commands for values that differ. Nothing changed means no command, and an unchanged account number or ledger account never runs into "may not be changed" once the bank book is in use.

The commands are separate requests: when one fails, the ones before it are already done. To send one command yourself, use `rename()`, `changeShortName()`, `changeBankAccount()` or `changeGeneralLedgerAccount()`.

## Code

```php
$twinfield = Example::twinfield();

$bankBooks = $twinfield->bankBooks();
$original = $bankBooks->get($argv[1] ?? 'EXAMPLE');

echo 'Before: ', $original->name, ', BIC ', $original->bankAccount->bic ?? '-', PHP_EOL;

try {
    $bankBook = $bankBooks->update(
        $original->edit()
            ->name($original->name . ' (updated)')
            ->bankAccount($original->bankAccount->edit()->bic(null)),
    );

    echo 'After:  ', $bankBook->name, ', BIC ', $bankBook->bankAccount->bic ?? '-', PHP_EOL;
} finally {
    $bankBooks->update($original->edit());
}
```

## Run

```sh
php examples/bank-books/update-bank-book/example.php
```

```text
Before: Example bank, BIC ABNANL2A
After:  Example bank (updated), BIC -
```
