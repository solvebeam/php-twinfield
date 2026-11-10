# Undo match

Undoes a match set of a customer, by its match number (UndoMatch on matching.asmx). Find the match number with the [List match sets](../list-match-sets/) example.

> **This example changes data in Twinfield.** Only run it on a test administration.

`undo()` takes the dimension type, the dimension and the match number: `DimensionTypeCode::AccountsReceivable` for a customer, `AccountsPayable` for a supplier, `Projects` for a project and `BalanceSheet` for a suspense account. `MatchCode::dimensionType()` gives the dimension type of a match code.

An unknown match number is a `MatchNotFoundException`; the other refusals, such as an unknown customer (`DimensionNotFoundByType`), a `MatchingException` with the message codes of the WSDL.

## Code

```php
$twinfield = Example::twinfield();

// E.g. `1000 12`: the customer and the match number, see the list-match-sets example.
[, $customer, $matchNumber] = array_pad($argv ?? [], 3, null);

if ($customer === null || $matchNumber === null) {
    echo 'Usage: php example.php <customer> <match number>', PHP_EOL;

    exit(0);
}

try {
    $twinfield->matching()->undo(DimensionTypeCode::AccountsReceivable, $customer, (int) $matchNumber);
} catch (MatchNotFoundException $exception) {
    echo 'Not found: ', $exception->messages[0]->text, PHP_EOL;

    exit(1);
} catch (MatchingException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Undid match ', $matchNumber, ' of customer ', $customer, PHP_EOL;
```

## Run

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/matching/undo-match/example.php 1000 12
```

The output below follows the documentation; it was not run live yet.

```text
Undid match 12 of customer 1000
```
