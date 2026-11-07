# Bulk import with errors

Saves three bank transactions in one request with `saveMany()`, where the second one has an account that does not exist. This changes data in Twinfield; the example deletes the saved transactions again.

Twinfield saves the valid transactions and refuses the others, so `saveMany()` does not throw: it returns a `SaveManyResult` with, per input in the same order, a `SavedTransaction` (with the transaction) or a `FailedTransaction` (with the errors and where they are). Use `saved()`, `failed()` and `allSaved()` to handle them.

## Code

```php
$twinfield = Example::twinfield();

$bank = $twinfield->bankTransactions();
$costs = Example::profitAndLossAccount($twinfield);
$base = BankTransactionInput::forBankBook(Example::bankBook($twinfield), new DateTimeImmutable());

// Three statements in one request; the second has an account that does not exist.
$result = $bank->saveMany(
    $base->statement(1)->pay($costs, '1.00', description: 'Example day 1'),
    $base->statement(2)->pay('NOPE9999', '2.00', description: 'Example day 2'),
    $base->statement(3)->pay($costs, '3.00', description: 'Example day 3'),
);

foreach ($result as $item) {
    echo sprintf('[%d/%d] ', $item->index + 1, count($result)), match (true) {
        $item instanceof SavedTransaction => 'saved as ' . $item->transaction->number(),
        default => 'failed: ' . $item->message(),
    }, PHP_EOL;
}

echo count($result->saved()), ' saved, ', count($result->failed()), ' failed', PHP_EOL;

// Clean up: delete the provisional example transactions again.
foreach ($result->saved() as $saved) {
    $bank->delete($saved->transaction->dayBook(), $saved->transaction->number(), 'php-twinfield example');
}
```

## Run

```sh
php examples/bank-transactions/bulk-import-with-errors/example.php
```

```text
[1/3] saved as 202600049
[2/3] failed: transactions/transaction[2]/lines/line[2]/dim1: Grootboekrekening NOPE9999 bestaat niet.
[3/3] saved as 202600050
2 saved, 1 failed
```
