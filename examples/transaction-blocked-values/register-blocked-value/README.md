# Register blocked value

Blocks part of a transaction line: associates a blocked value with one line of an existing transaction, e.g. the total line of a purchase invoice.

> **This example changes data in Twinfield.** Only run it on a test administration, and remove the blocked value again with [Unregister blocked value](../unregister-blocked-value/).

The command goes to the [TransactionBlockedValueService](https://developers.twinfield.com/documentation/api/transaction-data/transaction-blocked-value/), a Command & Query web service. It returns nothing; when Twinfield refuses it, you get a `CommandQueryFaultException` with `errorCode` `BlockedValueCannotBeRegistered` (see `TransactionBlockedValueServiceFaultCode`).

The line is the `id` of a line of the transaction: `$transaction->total->id` for the total line, `$line->id` for a detail line. The value has at most two decimals.

## Code

```php
$twinfield = Example::twinfield();

// E.g. `INK 202600021 1 50.00`: day book, transaction number, line id and the value to block.
[, $dayBook, $number, $line, $value] = array_pad($argv ?? [], 5, null);

if ($dayBook === null || $number === null || $line === null || $value === null) {
    echo 'Usage: php example.php <day book> <transaction number> <line id> <value>', PHP_EOL;

    exit(0);
}

try {
    $twinfield->transactionBlockedValues()->register($dayBook, (int) $number, (int) $line, Amount::fromString($value));
} catch (CommandQueryFaultException $exception) {
    echo 'Refused (', $exception->errorCode, '): ', $exception->errorMessage, PHP_EOL;

    exit(1);
}

echo 'Blocked ', $value, ' on line ', $line, ' of ', $dayBook, ' ', $number, PHP_EOL;
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/transaction-blocked-values/register-blocked-value/example.php INK 202600021 1 50.00
```

```text
Blocked 50.00 on line 1 of INK 202600021
```
