# Create cash book

Creates a cash book. This changes data in Twinfield, and **Twinfield has no command to delete a cash book**: the example always uses the code `EXAMPLE` (or the code from the command line), so a second run reports that it already exists.

`CashBookInput::create($code, $name)` takes the required values; add the rest with `shortName()` and `generalLedgerAccount()`. The documentation page says `CreateCashBook` has no short name, but the service accepts it (see the WSDL), so it is sent in the same command.

`create()` sends one `CreateCashBook` command and then reads the cash book. Twinfield itself refuses an existing code, which is thrown as `CashBookAlreadyExistsException`. Other refusals, such as an invalid ledger account, are a `CommandQueryFaultException` with a `CashBookServiceFaultCode`.

## Code

```php
$twinfield = Example::twinfield();

// Twinfield cannot delete cash books, so this example always uses the same code.
$input = CashBookInput::create($argv[1] ?? 'EXAMPLE', 'Example cash')
    ->shortName('Example');

try {
    $cashBook = $twinfield->cashBooks()->create($input);
} catch (CashBookAlreadyExistsException $exception) {
    echo 'Already exists: ', $exception->reason, PHP_EOL;

    exit(0);
}

echo 'Created: ', $cashBook->code, ' ', $cashBook->name, ' (', $cashBook->shortName ?? '-', ')', PHP_EOL;
```

## Run

```sh
php examples/cash-books/create-cash-book/example.php
```

```text
Created: EXAMPLE Example cash (Example)
```
