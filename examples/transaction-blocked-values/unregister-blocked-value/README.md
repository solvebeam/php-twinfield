# Unregister blocked value

Removes the blocked value from one line of an existing transaction, e.g. one registered with [Register blocked value](../register-blocked-value/).

> **This example changes data in Twinfield.** Only run it on a test administration.

The command goes to the [TransactionBlockedValueService](https://developers.twinfield.com/documentation/api/transaction-data/transaction-blocked-value/), a Command & Query web service. It returns nothing; when Twinfield refuses it, you get a `CommandQueryFaultException` with `errorCode` `BlockedValueCannotBeUnregistered` (see `TransactionBlockedValueServiceFaultCode`).

## Code

```php
$twinfield = Example::twinfield();

// E.g. `INK 202600021 1`: day book, transaction number and line id.
[, $dayBook, $number, $line] = array_pad($argv ?? [], 4, null);

if ($dayBook === null || $number === null || $line === null) {
    echo 'Usage: php example.php <day book> <transaction number> <line id>', PHP_EOL;

    exit(0);
}

try {
    $twinfield->transactionBlockedValues()->unregister($dayBook, (int) $number, (int) $line);
} catch (CommandQueryFaultException $exception) {
    echo 'Refused (', $exception->errorCode, '): ', $exception->errorMessage, PHP_EOL;

    exit(1);
}

echo 'Removed the blocked value from line ', $line, ' of ', $dayBook, ' ', $number, PHP_EOL;
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/transaction-blocked-values/unregister-blocked-value/example.php INK 202600021 1
```

```text
Removed the blocked value from line 1 of INK 202600021
```
