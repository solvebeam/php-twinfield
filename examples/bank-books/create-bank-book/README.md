# Create bank book

Creates a bank book with a bank account. This changes data in Twinfield, and **Twinfield has no command to delete a bank book**: the example always uses the code `EXAMPLE` (or the code from the command line), so a second run reports that it already exists.

`BankBookInput::create($code, $name)` takes the required values; add the rest with `shortName()`, `generalLedgerAccount()` and `bankAccount()`. `BankAccountInput` removes spaces from the IBAN, writes it in capitals and checks the check digits, so a typo fails before anything is sent.

`create()` sends one `CreateBankBook` command and then reads the bank book. Twinfield itself refuses an existing code, which is thrown as `BankBookAlreadyExistsException`. Other refusals, such as an invalid ledger account, are a `CommandQueryFaultException` with a `BankBookServiceFaultCode`.

## Code

```php
$twinfield = Example::twinfield();

// Twinfield cannot delete bank books, so this example always uses the same code.
$input = BankBookInput::create($argv[1] ?? 'EXAMPLE', 'Example bank')
    ->shortName('Example')
    ->bankAccount(
        BankAccountInput::create()
            ->name('Example B.V.')
            ->iban('NL91 ABNA 0417 1643 00')
            ->bic('ABNANL2A')
            ->country('NL'),
    );

try {
    $bankBook = $twinfield->bankBooks()->create($input);
} catch (BankBookAlreadyExistsException $exception) {
    echo 'Already exists: ', $exception->reason, PHP_EOL;

    exit(0);
}

echo 'Created: ', $bankBook->code, ' ', $bankBook->name, ' (', $bankBook->bankAccount->iban, ')', PHP_EOL;
```

## Run

```sh
php examples/bank-books/create-bank-book/example.php
```

```text
Created: EXAMPLE Example bank (NL91ABNA0417164300)
```
