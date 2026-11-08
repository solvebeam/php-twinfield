# Delete general ledger account

Deletes the account with the code you pass, or else a new example profit and loss account, and activates it again.

An account that is used in a transaction, or linked to a VAT code or article, cannot be deleted: Twinfield hides it instead, and `delete()` returns `DeleteResult::Hidden`. Hidden accounts are left out of searches, unless you use `includeHidden()`. `activate()` makes a deleted or hidden account active again.

## Code

```php
$twinfield = Example::twinfield();

$accounts = $twinfield->profitAndLossAccounts();

// The code from the command line, or else a new example account.
$example = !isset($argv[1]);
$code = $argv[1] ?? $accounts->create(
    GeneralLedgerAccountInput::profitAndLoss(
        Example::unusedGeneralLedgerAccountCode($accounts),
        'Example account to delete',
    ),
)->code;

$result = $accounts->delete($code);

echo match ($result) {
    DeleteResult::Deleted => 'Deleted ' . $code,
    // An account used in a transaction, or linked to a VAT code or article, is hidden instead.
    DeleteResult::Hidden => 'Hidden ' . $code . ': it is in use',
}, PHP_EOL;

echo 'Status: ', $accounts->get($code)->status->value, PHP_EOL;

// Deleted and hidden accounts can be made active again.
$accounts->activate($code);

echo 'Status: ', $accounts->get($code)->status->value, PHP_EOL;

// Clean up the example account.
if ($example) {
    $accounts->delete($code);
}
```

## Run

```sh
php examples/general-ledger-accounts/delete-general-ledger-account/example.php
```

```text
Deleted 8999
Status: deleted
Status: active
```
