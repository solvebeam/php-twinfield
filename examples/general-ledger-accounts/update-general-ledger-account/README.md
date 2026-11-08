# Update general ledger account

Creates a balance sheet account, makes it a matchable suspense account on which lines may have a customer or supplier, and deletes it again.

Start from `$account->edit()`: it has every current value, including the child validations and groups, so an update keeps what you do not change. Child validations and groups are sent as a whole: `childValidations()` without arguments removes them all. `update()` checks the account exists first (`DimensionNotFoundException`).

## Code

```php
$twinfield = Example::twinfield();

$accounts = $twinfield->balanceSheetAccounts();

$account = $accounts->create(
    GeneralLedgerAccountInput::balanceSheet(Example::unusedGeneralLedgerAccountCode($accounts), 'Suspense account'),
);

try {
    echo 'Before: ', $account->name, ', matchable: ', $account->matchable ? 'yes' : 'no',
        ', sub-analysis: ', $account->subAnalysis->name, PHP_EOL;

    // edit() starts from all current values; change only what you need.
    $account = $accounts->update(
        $account->edit()
            ->name('Suspense account bank')
            ->matchable()
            ->subAnalysis(SubAnalysis::Optional)
            ->childValidation(ChildValidation::dimensionType('DEB'))
            ->childValidation(ChildValidation::dimensionType('CRD')),
    );

    echo 'After:  ', $account->name, ', matchable: ', $account->matchable ? 'yes' : 'no',
        ', sub-analysis: ', $account->subAnalysis->name, ' (', implode(', ', array_map(
            static fn (ChildValidation $validation): string => $validation->value,
            $accounts->get($account->code)->childValidations,
        )), ')', PHP_EOL;
} finally {
    // Clean up: delete the example account again.
    $accounts->delete($account->code);
}
```

## Run

```sh
php examples/general-ledger-accounts/update-general-ledger-account/example.php
```

```text
Before: Suspense account, matchable: no, sub-analysis: NotAllowed
After:  Suspense account bank, matchable: yes, sub-analysis: Optional (DEB, CRD)
```
