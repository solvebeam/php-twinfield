# Create asset method

> **This example changes data in Twinfield.** It deletes the asset method again, but only run it on a test administration.

Creates an asset method for laptops, depreciated linearly over 36 periods, and deletes it again. It takes the accounts of an existing asset method, so they exist in your company. It uses the code you pass, or else the first unused code from `EXAMPLE999` down (`Example::unusedAssetMethodCode()`).

Describe the asset method with `AssetMethodInput`. Every method returns a new input:

| Method | What it sets |
|---|---|
| `AssetMethodInput::create($code, $name)` | Code (A-Z, 0-9, `_`, `-`, `+`) and name. |
| `shortName()` | Short name; `null` removes it. |
| `linear(60)` | The same amount every period, over 60 periods. |
| `linearPercentage('20')` | The same amount every period: 20% of the purchase value per year. |
| `reducingBalance('25')` | 25% of the remaining book value per year. |
| `noDepreciation()` | No depreciation, e.g. for land. |
| `depreciateFrom(DepreciationStart::FromPurchaseDate)` | From the purchase date, or from the revaluation date. |
| `purchaseValueAccount()`, `cumulativeDepreciationAccount()`, `assetsToActivateAccount()`, `revaluationAccount()`, `toBeInvoicedAccount()` | Balance sheet accounts. |
| `purchaseValueGroup()`, `depreciationGroup()` | Dimension groups of the balance sheet accounts. |
| `depreciationCostsAccount()`, `disposalResultAccount()`, `revaluationReserveAccount()` | Profit and loss accounts. |
| `freeText(1, 'Serial number')`, `freeText(2, 'Value', FreeTextType::Amount)` | Label and type of free text fields 1 to 5 of the fixed assets. |

The calculation methods replace each other, so you cannot combine a percentage with linear depreciation by accident. Accounts and free texts you leave out are sent empty.

Twinfield itself has one call for create and update. The SDK makes the difference explicit:

- `create()` reads first and throws an `AssetMethodAlreadyExistsException` when the code exists, so you never overwrite an asset method by accident.
- `update()` throws an `AssetMethodNotFoundException` when the code does not exist (see [Update asset method](../update-asset-method/)).
- `save()` does what Twinfield does, without the check.

All three read the asset method back afterwards, so you get the complete `AssetMethod`. Invalid input (a percentage with a comma, free text field 6) throws an `InvalidArgumentException` before anything is sent. When Twinfield refuses the asset method, for example because an account does not exist, you get a `ProcessXmlException` with the message per element.

## Code

```php
$twinfield = Example::twinfield();

$assetMethods = $twinfield->assetMethods();
$code = $argv[1] ?? Example::unusedAssetMethodCode($twinfield);

$method = $assetMethods->create(
    AssetMethodInput::create($code, 'Example laptops')
        ->linear(36)
        ->depreciateFrom(DepreciationStart::FromPurchaseDate)
        ->purchaseValueAccount($template->balanceAccounts->purchaseValue?->code)
        ->cumulativeDepreciationAccount($template->balanceAccounts->depreciation?->code)
        ->depreciationCostsAccount($template->profitLossAccounts->depreciation?->code)
        ->freeText(1, 'Serial number'),
);

echo 'Created ', $method->code, ' ', $method->name, ': ';
echo $method->periods, ' periods ', $method->calculationMethod->value, PHP_EOL;

// Clean up: delete the example asset method again.
$assetMethods->delete($method->code);
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/asset-methods/create-asset-method/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
Created EXAMPLE999 Example laptops: 36 periods linear
Deleted EXAMPLE999
```
