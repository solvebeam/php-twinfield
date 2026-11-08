# Create general ledger account

Creates a profit and loss account with a free code, on which every line needs a cost center, and deletes it again.

`GeneralLedgerAccountInput::profitAndLoss()` and `::balanceSheet()` take the code and name; the input knows its type, so `balanceSheetAccounts()->create()` refuses a profit and loss input. Only what you set is sent; the rest gets the defaults of the dimension type. `create()` reads first, so an existing account is never overwritten (`DimensionAlreadyExistsException`); `save()` creates or updates in one request.

```php
GeneralLedgerAccountInput::profitAndLoss('8010', 'Revenue services')
    ->vatCode('VH', fixed: true)                         // lines can only use VH
    ->subAnalysis(SubAnalysis::Optional)
    ->childValidation(ChildValidation::dimensionType('KPL'))
    ->childValidation(ChildValidation::dimension('P0001', 3))
    ->group('REVENUE')
    ->activeFrom(FinancialPeriod::of(2026, 1));
```

Twinfield refusals, e.g. a code outside the code format of the company, are thrown as a `ProcessXmlException` with the messages per element.

## Code

```php
$twinfield = Example::twinfield();

$accounts = $twinfield->profitAndLossAccounts();

// A free code in the format of the company, e.g. 8999.
$code = Example::unusedGeneralLedgerAccountCode($accounts);

$input = GeneralLedgerAccountInput::profitAndLoss($code, 'Revenue consultancy')
    ->shortName('Consultancy')
    // Every line needs a cost center (dimension 2).
    ->subAnalysis(SubAnalysis::Required)
    ->childValidation(ChildValidation::dimensionType('KPL'));

try {
    $account = $accounts->create($input);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Created ', $account->code, ' ', $account->name, PHP_EOL;
echo 'Sub-analysis: ', $account->subAnalysis->name, ', ', $account->childValidations[0]->value ?? '-', PHP_EOL;

// Clean up: delete the example account again.
$accounts->delete($account->code);

echo 'Deleted ', $account->code, PHP_EOL;
```

## Run

```sh
php examples/general-ledger-accounts/create-general-ledger-account/example.php
```

```text
Created 8999 Revenue consultancy
Sub-analysis: Required, KPL
Deleted 8999
```
