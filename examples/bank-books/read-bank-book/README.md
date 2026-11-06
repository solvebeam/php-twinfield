# Read bank book

Reads one bank book: the code from the command line, or else the first bank book.

`get()` returns a `BankBook`, or throws a `BankBookNotFoundException` when the code does not exist. Use `find()` to get `null` instead. Cash books are not bank books: `get('KAS')` throws as well.

- `generalLedgerAccount`: the balance sheet account of the bank; `null` when not set, as on the default bank book `BNK` of a new company.
- `bankAccount`: a `BankAccount` with `name`, `number`, `iban`, `bic`, `nbic`, `country` and `referenceNumber`. Every field is `null` when not set.
- `automaticBankProcessing`: whether statements, payments and direct debits go through the bank automatically, and the `signUpState` (`Inactive`, `Pending`, `Active`). Read-only.

## Code

```php
$twinfield = Example::twinfield();

// The code from the command line, or else the first bank book.
$code = $argv[1] ?? $twinfield->bankBooks()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no bank books.' . PHP_EOL);

try {
    $bankBook = $twinfield->bankBooks()->get($code);
} catch (BankBookNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$account = $bankBook->bankAccount;
$processing = $bankBook->automaticBankProcessing;

echo 'Code:            ', $bankBook->code, PHP_EOL;
echo 'Name:            ', $bankBook->name, PHP_EOL;
echo 'Short name:      ', $bankBook->shortName ?? '-', PHP_EOL;
echo 'Ledger account:  ', $bankBook->generalLedgerAccount ?? '-', PHP_EOL;
echo 'Account holder:  ', $account->name ?? '-', PHP_EOL;
echo 'Account number:  ', $account->number ?? '-', PHP_EOL;
echo 'IBAN:            ', $account->iban ?? '-', PHP_EOL;
echo 'BIC:             ', $account->bic ?? '-', PHP_EOL;
echo 'Country:         ', $account->country ?? '-', PHP_EOL;
echo 'Automatic:       ', $processing->signUpState->value, PHP_EOL;
echo '  statements:    ', $processing->processStatements ? 'yes' : 'no', PHP_EOL;
echo '  payments:      ', $processing->processPayments ? 'yes' : 'no', PHP_EOL;
echo '  direct debits: ', $processing->processDirectDebits ? 'yes' : 'no', PHP_EOL;
```

## Run

```sh
php examples/bank-books/read-bank-book/example.php ING
```

```text
Code:            ING
Name:            ING 3255112
Short name:      -
Ledger account:  1100
Account holder:  Example B.V.
Account number:  3255112
IBAN:            NL45INGB0003255112
BIC:             -
Country:         NL
Automatic:       Inactive
  statements:    no
  payments:      no
  direct debits: no
```
