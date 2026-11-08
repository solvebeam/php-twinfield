# Read general ledger account

Reads one general ledger account, by the code you pass or else the first profit and loss account, and shows its settings.

A code belongs to a balance sheet account or a profit and loss account, so the example tries both with `find()`, which gives null when the code is not of that type. `get()` throws a `DimensionNotFoundException` instead.

`GeneralLedgerAccount` is strict: required values are never null, and optional values (short name, VAT code, periods) are null when they are not set. The settings:

- `subAnalysis`: whether lines need a dimension 2, e.g. a cost center: `Required`, `NotAllowed` or `Optional`.
- `childValidations`: which dimensions lines may have below the account, e.g. type `KPL` (cost centers) on level 2.
- `matchable`, `vatCode` (with `vatCodeFixed`), `groups`, `activeFrom` and `activeUntil`.

## Code

```php
$twinfield = Example::twinfield();

// The code from the command line, or else the first profit and loss account.
$code = $argv[1] ?? $twinfield->profitAndLossAccounts()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no profit and loss accounts.' . PHP_EOL);

// A code is a balance sheet or a profit and loss account: find() gives null for the other type.
$account = $twinfield->balanceSheetAccounts()->find($code)
    ?? $twinfield->profitAndLossAccounts()->find($code)
    ?? exit('No general ledger account ' . $code . PHP_EOL);

echo 'Code:          ', $account->code, PHP_EOL;
echo 'Name:          ', $account->name, PHP_EOL;
echo 'Type:          ', $account->type->label(), ' (', $account->type->value, ')', PHP_EOL;
echo 'Status:        ', $account->status->value, $account->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Matchable:     ', $account->matchable ? 'yes' : 'no', PHP_EOL;
echo 'Sub-analysis:  ', $account->subAnalysis->name, PHP_EOL;
echo 'Validations:   ', implode(', ', array_map(
    static fn (ChildValidation $validation): string => $validation->type->value . ' ' . $validation->value,
    $account->childValidations,
)) ?: '-', PHP_EOL;
echo 'VAT code:      ', $account->vatCode?->code ?? '-', $account->vatCodeFixed ? ' (fixed)' : '', PHP_EOL;
echo 'Groups:        ', implode(', ', $account->groups) ?: '-', PHP_EOL;
echo 'Modified:      ', $account->modified?->format('Y-m-d H:i') ?? '-', PHP_EOL;
```

## Run

```sh
php examples/general-ledger-accounts/read-general-ledger-account/example.php
```

```text
Code:          40000
Name:          Brutolonen en salarissen
Type:          profit and loss account (PNL)
Status:        active (in use)
Matchable:     no
Sub-analysis:  Optional
Validations:   type KPL
VAT code:      -
Groups:        -
Modified:      2014-12-19 07:14
```

```sh
php examples/general-ledger-accounts/read-general-ledger-account/example.php 0100
```

```text
Code:          0100
Name:          Oprichtingskosten
Type:          balance sheet account (BAS)
Status:        hide (in use)
Matchable:     no
Sub-analysis:  NotAllowed
Validations:   -
VAT code:      -
Groups:        -
Modified:      2014-12-19 07:04
```
