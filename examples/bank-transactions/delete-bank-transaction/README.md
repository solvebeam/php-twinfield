# Delete bank transaction

Creates a provisional bank transaction and deletes it. This changes data in Twinfield.

`delete($dayBook, $number, $reason)` needs a reason, which Twinfield keeps. Only provisional transactions can be deleted: for a final transaction Twinfield refuses, which is thrown as `ProcessXmlException`. Correct a final transaction with a new transaction instead.

## Code

```php
$twinfield = Example::twinfield();

$bank = $twinfield->bankTransactions();

$transaction = $bank->create(
    BankTransactionInput::forBankBook(Example::bankBook($twinfield), new DateTimeImmutable())
        ->pay(Example::profitAndLossAccount($twinfield), '1.00', description: 'Entered twice'),
);

// Only provisional transactions can be deleted; Twinfield wants a reason.
$bank->delete($transaction->dayBook(), $transaction->number(), 'Entered twice');

echo 'Deleted ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
echo 'Still there: ', $bank->find($transaction->dayBook(), $transaction->number()) === null ? 'no' : 'yes', PHP_EOL;
```

## Run

```sh
php examples/bank-transactions/delete-bank-transaction/example.php
```

```text
Deleted ING 202600048
Still there: no
```
