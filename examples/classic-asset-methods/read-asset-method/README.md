# Read asset method

Reads all settings of one asset method: the code you pass, or else the first one.

`get()` returns an `AssetMethod`; `find()` returns `null` instead of throwing when the code does not exist. Twinfield has no `<read>` request for asset methods: the SDK sends an `<assetmethod>` with only the company and the code, which is how Twinfield reads one.

| Property | What it holds |
|---|---|
| `code`, `name`, `shortName` | The asset method; `shortName` is `null` when not set. |
| `status`, `inUse` | `AssetMethodStatus` (active, deleted, hidden), and whether fixed assets use it. |
| `calculationMethod`, `periods`, `percentage` | `CalculationMethod` (none, linear, linear percentage, reducing balance), the number of periods, and the percentage per year (`null` for linear). |
| `depreciationStart` | `DepreciationStart`: from the revaluation date or back to the purchase date. |
| `balanceAccounts`, `profitLossAccounts` | Each account as an `AccountReference` with code, name and dimension type, or `null` when not set. |
| `freeTexts` / `freeText(1)` | The five free text fields of the fixed assets: label (or `null`) and type (text or amount). |
| `created`, `modified`, `modifiedBy`, `touched` | When and by whom it was changed, and how often. |

When Twinfield returns something the SDK does not expect, such as an unknown calculation method, you get an `UnexpectedResponseException` with the path of the element, instead of a silent default.

## Code

```php
$twinfield = Example::twinfield();

try {
    $method = $twinfield->classicAssetMethods()->get($code);
} catch (AssetMethodNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo 'Calculation:   ', $method->calculationMethod->value, PHP_EOL;
echo 'Periods:       ', $method->periods, PHP_EOL;
echo 'Purchase:      ', $method->balanceAccounts->purchaseValue?->code ?? '-', PHP_EOL;
```

## Run

```sh
php examples/classic-asset-methods/read-asset-method/example.php
php examples/classic-asset-methods/read-asset-method/example.php MACHINE
```

```text
Code:          MACHINE
Name:          Machines 5 years linear
Status:        active (in use)
Calculation:   linear
Periods:       60
Percentage:    -
Depreciate:    from_reconciliation
Purchase:      0501 Machines purchase value
Cumulative:    0135 Machines cumulative depreciation
Costs:         4730 Depreciation costs machines
Disposal:      -
Free text 1:   Serial number (text)
```
