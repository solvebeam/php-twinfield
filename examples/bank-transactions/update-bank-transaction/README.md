# Update bank transaction

Creates a provisional bank transaction, changes a line and adds one, then deletes it again. This changes data in Twinfield.

Start from `$transaction->edit()`: it returns an input with all values of the transaction, including the number, so `update()` changes this transaction instead of creating a new one. Then change what you need:

- `mapLines()` changes the detail lines one by one, e.g. a description;
- `receive()`, `pay()` and `line()` add lines; `withLines()` replaces them all;
- the total line and the closing balance are calculated again.

`update()` reads the transaction first and throws a `TransactionNotFoundException` when it does not exist. Only provisional transactions can be changed.

## Code

```php
$twinfield = Example::twinfield();

$bank = $twinfield->bankTransactions();
$costs = Example::profitAndLossAccount($twinfield);

$created = $bank->create(
    BankTransactionInput::forBankBook(Example::bankBook($twinfield), new DateTimeImmutable())
        ->pay($costs, '12.50', description: 'Bank costs'),
);

echo 'Created: ', $created->number(), ' ', $created->details[0]->description, ' ', $created->balance(), PHP_EOL;

// Start from edit(), so all current values are kept; change the line and add one.
$updated = $bank->update(
    $created->edit()
        ->mapLines(static fn (DetailLineInput $line): DetailLineInput => $line->description('Bank costs October'))
        ->pay($costs, '2.50', description: 'Bank costs November'),
);

echo 'Updated: ', $updated->number(), ' ', count($updated->details), ' lines, ', $updated->balance(), PHP_EOL;

foreach ($updated->details as $line) {
    echo '  ', $line->description, ': ', $line->amounts->value, PHP_EOL;
}

// Clean up: delete the provisional example transaction again.
$bank->delete($updated->dayBook(), $updated->number(), 'php-twinfield example');
```

## Run

```sh
php examples/bank-transactions/update-bank-transaction/example.php
```

```text
Created: 202600047 Bank costs -12.50
Updated: 202600047 2 lines, -15.00
  Bank costs October: 12.50
  Bank costs November: 2.50
```
