# Create bank transaction

Creates a bank transaction for one bank statement, then deletes it again. This changes data in Twinfield.

`BankTransactionInput::create($dayBook, $account, $date)` takes the bank book, its ledger account and the date; `forBankBook()` takes the code and ledger account from a `BankBook`. Then add a line per bank line:

- `receive($account, $amount, …)`: money comes in, e.g. a customer paying an invoice;
- `pay($account, $amount, …)`: money goes out, e.g. a supplier invoice or a cost.

You never write `debit` or `credit` yourself: `receive()` and `pay()` choose the side, and the input calculates the total line on the bank account and the closing balance from the opening balance in `statement()`. So the transaction always balances.

New transactions are **provisional** (`destiny="temporary"`): you can still change, post or delete them. Call `final()` to save them as final at once.

The example uses the first bank book with a ledger account and the first profit and loss account of the company, so it runs in any administration.

## Code

```php
$twinfield = Example::twinfield();

$bank = $twinfield->bankTransactions();
$bankBook = Example::bankBook($twinfield);
$costs = Example::profitAndLossAccount($twinfield);

$transaction = $bank->create(
    BankTransactionInput::forBankBook($bankBook, new DateTimeImmutable())
        ->statement(1, startValue: '100.00')
        ->description('Example statement')
        ->pay($costs, '12.50', description: 'Example bank costs'),
);

echo 'Created:  ', $transaction->dayBook(), ' ', $transaction->number(),
    ' (', $transaction->header->status->name, ')', PHP_EOL;
echo 'Total:    ', $transaction->total->debitCredit->value, ' ', $transaction->total->amounts->value, PHP_EOL;
echo 'Balance:  ', $transaction->startValue, ' → ', $transaction->closeValue, PHP_EOL;

// Clean up: delete the provisional example transaction again.
$bank->delete($transaction->dayBook(), $transaction->number(), 'php-twinfield example');

echo 'Deleted:  ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
```

## Run

```sh
php examples/bank-transactions/create-bank-transaction/example.php
```

```text
Created:  ING 202600046 (Provisional)
Total:    credit 12.50
Balance:  100.00 → 87.50
Deleted:  ING 202600046
```
