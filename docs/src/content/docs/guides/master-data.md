---
title: Master data (dimensions)
description: Search, read, create, update and delete master data such as customers, general ledger accounts, activities, projects, cost centers, fixed assets and offices, with typed models and clear errors.
---

Master data in Twinfield, such as activities, projects, customers and suppliers, are **dimensions**. Each has a dimension type, e.g. `ACT` for activities. The SDK searches them with the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) and reads and writes them with the [XML web services](/guides/process-xml/). You work with typed objects and never write XML yourself.

| Dimension type | Resource | Status |
|---|---|---|
| Activities (`ACT`) | `$twinfield->activities()` | Search, read, create, update, delete, activate |
| [Cost centers](#cost-centers) (`KPL`) | `$twinfield->costCenters()` | Search, read, create, update, delete, activate |
| [Customers](#customers) (`DEB`) | `$twinfield->customers()` | Search (also by bank account and address), read, create, update, delete, activate |
| [Fixed assets](#fixed-assets) (`AST`) | `$twinfield->fixedAssets()` | Search (also by group), read, create, update, delete, activate |
| [General ledger accounts](#general-ledger-accounts) (`BAS`, `PNL`) | `$twinfield->balanceSheetAccounts()`, `$twinfield->profitAndLossAccounts()` | Search (also hidden), read, create, update, delete, activate |
| [Projects](#projects) (`PRJ`) | `$twinfield->projects()` | Search, read, create, update, delete, activate |
| [Suppliers](#suppliers) (`CRD`) | `$twinfield->suppliers()` | Search (also by bank account and address), read, create, update, delete, activate |

Other master data works the same way, but is not a dimension:

| Master data | Resource | Status |
|---|---|---|
| [Asset methods](#asset-methods) | `$twinfield->assetMethods()` | Search, read, create, update, delete, activate |
| [Bank books](#bank-books) | `$twinfield->bankBooks()` | Search, read, create, update |
| [Cash books](#cash-books) | `$twinfield->cashBooks()` | Search, read, create, update |
| [Currencies](#currencies) | `$twinfield->currencies()` | Search, read, create, update, delete; exchange rates |
| [Dimension groups](#dimension-groups) | `$twinfield->dimensionGroups()` | Search, read, create, update, delete |
| [Dimension types](#dimension-types) | `$twinfield->dimensionTypes()` | Search, read, update (no create or delete) |
| [Budgets](#budgets) | `$twinfield->budgets()` | Search, budget vs. actual (read-only) |
| [Offices](#offices) | `$twinfield->offices()` | List, search, read (read-only) |
| [Users](#users) | `$twinfield->users()` | List, search, read (also the user of the access token), create, update, delete, activate |

Dimensions belong to a company, so the client needs one: `Twinfield::create($accessTokenProvider)->company('1000')`.

## At a glance

```php
use SolveBeam\Twinfield\Activity\ActivityInput;
use SolveBeam\Twinfield\Dimension\ChangeMode;
use SolveBeam\Twinfield\Dimension\Quantity;

$activities = $twinfield->activities();

// Search
foreach ($activities->search('A*')->inCode()->all() as $summary) {
    echo $summary->code, ' ', $summary->name, PHP_EOL;
}

// Read
$activity = $activities->get('A001');   // or find(): null when it does not exist

// Create
$activities->create(
    ActivityInput::create('A100', 'Consultancy')
        ->customer('1010')
        ->rate('T001', ChangeMode::Disallow)
        ->billable(true)
        ->quantity(Quantity::create('KM')->rate('KM')),
);

// Update
$activities->update($activity->edit()->name('Development'));

// Delete
$activities->delete('A100');
```

## Searching

`search($pattern)` returns an immutable `CodeNameSearch`: every method gives a new search, so you can keep a base search and refine it.

| Method | What it does |
|---|---|
| `search('A*')` | Pattern: `*` matches anything, `?` one character. Default `*`. |
| `inCodeAndName()` / `inCode()` / `inName()` | Where the pattern must match. Default: code and name. |
| `modifiedSince($date)` | Only dimensions changed on or after this moment. |
| `group('DEV*')` | Only dimensions in a dimension group; wildcards are allowed. |
| `limit(10)` / `offset(20)` | Page size (default 100) and results to skip. |
| `get()` | One page: `totalRows` and the summaries (code and name). |
| `all()` | All results over all pages, page by page while you iterate. |
| `count()` | Number of matches, with one small request. |

Search results only have the code and name. Use `get($code)` for everything else.

## Reading

`get($code)` returns the full read model, e.g. an `Activity`. Read models are strict: required values are never `null`, and a response that does not match what the SDK expects throws an `UnexpectedResponseException` with the path, e.g. `dimension/projects/customer/@locked is missing.` A value is only `null` when Twinfield allows it to be empty, such as a short name that was never set.

When the code does not exist, `get()` throws a `DimensionNotFoundException` with `dimensionType`, `dimensionCode` and Twinfield's `reason`. `find($code)` returns `null` instead. A code that does not match the company's code format (for activities by default `A[0-9][0-9][0-9]`) cannot exist, so it is also "not found".

## Creating and updating

You describe what to save with an input object, e.g. `ActivityInput`. It is separate from the read model: you can only set what Twinfield lets you change, and invalid values (a name that is too long, a code with lowercase letters) throw an `InvalidArgumentException` before anything is sent.

Twinfield has one request for both: it creates the dimension when the code is new and changes it otherwise. The SDK makes the difference explicit:

| Method | When the code is new | When the code exists | Requests |
|---|---|---|---|
| `create($input)` | Creates | Throws `DimensionAlreadyExistsException` | Read + save |
| `update($input)` | Throws `DimensionNotFoundException` | Changes | Read + save |
| `save($input)` | Creates | Changes | Save |

To change a dimension, start from what Twinfield has: `$activity->edit()` gives an input with all current values. The SDK always sends every value, so a value you remove (e.g. `shortName(null)`) is also removed in Twinfield.

All three return the saved read model from Twinfield's response. When Twinfield refuses the request, you get a `ProcessXmlException` with the message per element, e.g. `dimension/financials/vatcode: Btw-code XX bestaat niet.`

## Who may change a setting: ChangeMode

Time & expenses settings such as the customer, rate, authoriser and billable have a `ChangeMode`: whether users may change the value in their timesheet, or whether it comes from another level. An activity inherits from its project or the user, a [project](#projects) from the activity or the user.

| ChangeMode | Meaning | XML |
|---|---|---|
| `Allow` | Fixed value, users may change it | `locked="false" inherit="false"` |
| `Disallow` | Fixed value, users may not change it | `locked="true" inherit="false"` |
| `Inherit` | From another level (see above) | `locked="true" inherit="true"` |

New activities and projects inherit every setting until you set it. Inherited settings have no value of their own, so `code` (and `billable`) are `null`.

## Deleting

`delete($code)` returns what Twinfield did:

- `DeleteResult::Deleted`: the dimension is deleted (status `deleted`).
- `DeleteResult::Hidden`: the dimension is in use, so Twinfield hid it instead (status `hide`).

`activate($code)` makes a deleted or hidden dimension active again. A deleted dimension keeps its code: `create()` with the same code throws `DimensionAlreadyExistsException`.

## Cost centers

Cost centers (dimension type `KPL`, level 2) are the simplest dimension: only the **name** (at most 80 characters) can be set. `$twinfield->costCenters()` has the same methods as activities: `search()`, `get()`, `find()`, `create()`, `update()`, `save()`, `delete()` and `activate()`.

```php
use SolveBeam\Twinfield\CostCenter\CostCenterInput;

$costCenters = $twinfield->costCenters();

foreach ($costCenters->search('1*')->inCode()->all() as $summary) {
    echo $summary->code, ' ', $summary->name, PHP_EOL;
}

$costCenter = $costCenters->create(CostCenterInput::create('400', 'Marketing'));

$costCenters->update($costCenter->edit()->name('Sales & marketing'));

$costCenters->delete('400'); // DeleteResult::Hidden when it is used in a transaction
```

- The read model `CostCenter` has `code`, `name`, `uid`, `status`, `inUse`, `behaviour`, `touched` and `modified`. Twinfield documents the other dimension fields (short name, periods, website, chamber of commerce and VAT number) as "not in use" for cost centers, so the SDK leaves them out.
- The code format differs per company (in Twinfield under dimension types, `KPL`). The SDK only checks that the code is 1–16 characters `A-Z`, `0-9`, `_`, `-` or `+`. When Twinfield refuses a code on `create()` or `save()`, you get a `ProcessXmlException` with the message on `dimension/code`.
- Use cost centers as `dim2` on transaction lines (`costCenter:` on [transactions](/guides/transactions/)) and per cost center in budgets: `$twinfield->budgets()->totals($code, 2025)->byCostCenter()`.

## Customers

Customers (dimension type `DEB`, level 2) have the richest model of all dimensions. `$twinfield->customers()` has `search()`, `get()`, `find()`, `create()`, `update()`, `save()`, `delete()` and `activate()`.

```php
use SolveBeam\Twinfield\Customer\CollectionSchema;
use SolveBeam\Twinfield\Customer\CustomerInput;
use SolveBeam\Twinfield\Customer\SendReminder;
use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\BankInput;

$customers = $twinfield->customers();

// Search on code and name, bank account number or address fields
$customers->search('*Example*')->inName()->all();
$customers->search('NL91ABNA*')->inBankAccount()->all();
$customers->search('*Amsterdam*')->inAddress()->all();

// Read
$customer = $customers->get('1000');
echo $customer->financials->dueDays, ' ', $customer->defaultAddress()?->city, ' ', $customer->vatNumber(), PHP_EOL;

// Create: without code(), Twinfield gives the next free code
$customer = $customers->create(
    CustomerInput::create('Example Trading B.V.')
        ->dueDays(30)
        ->vatCode('VH')
        ->eInvoicing('invoices@example.com')
        ->directDebit('SEPANLDD')
        ->collectMandate('MANDATE-001', new DateTimeImmutable('2026-01-15'))
        ->collectionSchema(CollectionSchema::Core)
        ->creditLimit('5000.00')
        ->sendReminder(SendReminder::ByEmail, 'finance@example.com')
        ->address(
            AddressInput::invoice('Example Trading B.V.')
                ->field(2, 'Damrak 1')
                ->postcode('1012 AB')
                ->city('Amsterdam')
                ->country('NL')
                ->vatNumber('NL001234567B01')
                ->default(),
        )
        ->bank(BankInput::create()->iban('NL91 ABNA 0417 1643 00')->ascription('Example Trading B.V.')->default())
        ->paymentCondition(8, '2'),
);

// Update: edit() carries every value, including addresses and banks with their ids
$customers->update(
    $customer->edit()
        ->dueDays(14)
        ->address($customer->defaultAddress()->edit()->city('Rotterdam'))
        ->withoutBank(1),
);

$customers->delete($customer->code); // DeleteResult::Hidden when it is used in a transaction
```

The read model `Customer` groups the settings like Twinfield does:

| Property | Contents |
|---|---|
| `code`, `name`, `shortName`, `website`, `status`, `inUse`, `uid`, `modified` | The dimension itself |
| `activeFrom`, `activeUntil` | `FinancialPeriod`s, `null` for no limit (Twinfield's `0`) |
| `financials` | `dueDays`, `directDebit`, `meansOfPayment`, `payCode`, `eInvoicing`, `eInvoicingEmail`, `balanceAccount`, `vatCode`, `collectMandate` (SEPA mandate id and signature date), `collectionSchema` (`core`, `b2b`) |
| `creditManagement` | `creditLimit` (an `Amount`), `sendReminder`, `reminderEmail`, `responsibleUser`, `blocked`, `rightOfUse`, `segmentCode`, `comment` |
| `remittanceAdvice` | `sendType` and `email` |
| `addresses` | `Address`es: `type` (`invoice`, `postal`, `contact`), `default`, `name`, `country`, `city`, `postcode`, `telephone`, `telefax`, `email` and the free fields `field1`–`field6` |
| `banks` | `Bank`s: `iban`, `bic`, `accountNumber`, `ascription`, `bankName`, `nationalBankCode`, the bank address, `default` and the read-only `blocked` |
| `groups`, `postingRules`, `paymentConditions`, `discountArticle` | Dimension groups, posting rules with lines, payment discounts, the discount article |

- Optional values are `null` when they are not set; required values (such as the balance sheet account) are never `null`, and an unexpected response throws an `UnexpectedResponseException`.
- `defaultAddress()`, `defaultBank()`, `address($id)`, `bank($id)`, `addressesOfType($type)`, `vatNumber()` and `chamberOfCommerceNumber()` are shortcuts.
- The labels of the address fields are set per dimension type. By default field 1 is "attention of", 2 and 3 are address lines, 4 is the VAT number (only valid VAT numbers are accepted) and 5 the chamber of commerce number. `AddressInput` has `vatNumber()` and `chamberOfCommerceNumber()` for fields 4 and 5.
- `CustomerInput` is immutable and only sends what you set: when creating, everything else gets the default of the dimension type; when updating, it stays as it is. Setting a value to `null` clears it.
- Settings that belong together are set together, so they cannot contradict each other: `directDebit($payCode)` also sets the means of payment to a payment file, `withoutDirectDebit()` to none; `sendReminder(SendReminder::ByEmail, $email)` requires the email address.
- Addresses, bank accounts, groups and payment conditions are sent as a whole once set. `address()` and `bank()` replace the entry with the same id, or add one; a new default makes the others non-default. `withoutAddress($id)` and `withoutBank($id)` remove one; `withoutPostingRule($id)` deletes a posting rule (`status="deleted"`).
- IBANs are normalised (spaces removed, upper case) and checked with the ISO 13616 checksum. Amounts are numeric strings or `Amount`s, never floats.
- `Address`, `Bank`, `PostingRule`, `PaymentCondition` and `RemittanceAdvice` live in `SolveBeam\Twinfield\Dimension`, as [suppliers](#suppliers) (`CRD`) have them too.

Differences with the [documentation](https://developers.twinfield.com/documentation/api/master-data/customers/) that the SDK handles:

| Documentation | Twinfield |
|---|---|
| `<invoicing>`, `<groups>`, `<postingrules>` and `<paymentconditions>` | Left out of the response when empty |
| Posting rule lines have `dimension2` and `dimension3` | Left out when empty |
| A posting rule has a currency and amount | A rule can have an empty `<currency/>`, `<amount/>` and `<lines/>` |
| An address has a name | The name can be empty |
| `<remittanceadvice>` is not listed in the root elements | It is in every read response |
| A posting rule line has a ratio and a VAT code | Seen on suppliers, which share the posting rules: the ratio can be empty and the VAT code can be `#` (no VAT); see [Suppliers](/guides/documentation-differences/#suppliers) |

## Suppliers

Suppliers (dimension type `CRD`, level 2) are the counterpart of customers: the same addresses, bank accounts, groups, posting rules and payment discounts, but with financials for paying instead of collecting. `$twinfield->suppliers()` has `search()`, `get()`, `find()`, `create()`, `update()`, `save()`, `delete()` and `activate()`.

```php
use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Dimension\PostingRuleInput;
use SolveBeam\Twinfield\Dimension\PostingRuleLine;
use SolveBeam\Twinfield\Dimension\RemittanceAdviceSendType;
use SolveBeam\Twinfield\Supplier\MeansOfPayment;
use SolveBeam\Twinfield\Supplier\SupplierInput;

$suppliers = $twinfield->suppliers();

// Search on code and name, bank account number or address fields
$suppliers->search('*Office*')->inName()->all();
$suppliers->search('NL91ABNA*')->inBankAccount()->all();

// Read
$supplier = $suppliers->get('2000');
echo $supplier->financials->dueDays, ' ', $supplier->defaultBank()?->iban, PHP_EOL;

if ($supplier->financials->meansOfPayment === MeansOfPayment::PaymentFile) {
    echo 'Paid by credit transfer with ', $supplier->financials->payCode, PHP_EOL;
}

// Create: without code(), Twinfield gives the next free code
$supplier = $suppliers->create(
    SupplierInput::create('Example Office Supplies B.V.')
        ->dueDays(14)
        ->vatCode('IH')
        ->relationsReference('CUST-4711')      // your customer number at the supplier
        ->creditTransfer('SEPANLCT')           // or cheques(), or withoutPayments()
        ->remittanceAdvice(RemittanceAdviceSendType::ByEmail, 'accounts@example.com')
        ->address(AddressInput::invoice('Example Office Supplies B.V.')->field(2, 'Oudegracht 1')->city('Utrecht')->country('NL')->default())
        ->bank(BankInput::create()->iban('NL91 ABNA 0417 1643 00')->ascription('Example Office Supplies B.V.')->default())
        ->postingRule(
            PostingRuleInput::create('EUR', '121.00')
                ->line(PostingRuleLine::create('4500', '80')->costCenter('100')->vatCode('IH'))
                ->line(PostingRuleLine::create('4510', '20')->vatCode('IH')),
        )
        ->paymentCondition(5, '2'),
);

// Update: edit() carries every value, including addresses and banks with their ids
$suppliers->update($supplier->edit()->withoutPayments()->withoutBank(1));

$suppliers->delete($supplier->code); // DeleteResult::Hidden when it is used in a transaction
```

The read model `Supplier` has the same properties and shortcuts as `Customer` (`defaultAddress()`, `defaultBank()`, `address($id)`, `bank($id)`, `addressesOfType($type)`, `vatNumber()`, `chamberOfCommerceNumber()`), with these differences:

| Property | Contents |
|---|---|
| `financials` | `dueDays`, `payAvailable`, `meansOfPayment` (`None`, `PaymentFile`, `Cheques`), `payCode`, `balanceAccount`, `relationsReference` (external supplier number), `vatCode` |
| `remittanceAdvice` | `sendType` (`ToFileManager`, `ByEmail`) and `email` |
| `banks` | `Bank`s as for customers, plus the read-only `blocked`, and `wayOfPayment` and `payAddress` (payment type BTL91 only) |
| – | No credit management, e-invoicing, SEPA mandate or discount article: Twinfield does not use them for suppliers |

- How the supplier is paid is set in one call, so the settings cannot contradict each other: `creditTransfer($payCode)` (`payavailable` true, `meansofpayment` paymentfile), `cheques()` (true, cheques, no pay code) and `withoutPayments()` (false, none, no pay code).
- `BankInput::wayOfPayment(WayOfPayment::Cheque, PayAddress::Beneficiary)` sets the way of payment of a bank account for the payment type BTL91; a pay address is only accepted with a cheque. They are only sent when set.
- A posting rule proposes how a purchase invoice is booked. A line without a fixed share has `ratio` null; `PostingRuleLine::create('4500', null)` creates one.
- The VAT code `#` (no VAT) is accepted wherever a VAT code is, in `vatCode()` and in posting rule lines.
- `SupplierInput` works like `CustomerInput`: immutable, only what you set is sent, null clears a value, and lists are sent as a whole once set.

Differences with the [documentation](https://developers.twinfield.com/documentation/api/master-data/suppliers/) that the SDK handles, seen in 184 live suppliers. See [Differences with the documentation](/guides/documentation-differences/#suppliers) for how they were found, the mistakes in the documentation examples, and what is not yet verified:

| Documentation | Twinfield |
|---|---|
| `<sendtype>` and `<sendmail>` are listed under `<dimension>` | They are in `<remittanceadvice>`, which is in every read response |
| `<collectmandate>` and `<collectionschema>` are not in use | They are returned (the mandate id is the supplier code, the schema `core`); the SDK leaves them out |
| A bank has `wayofpayment` and `payaddress` | Left out of every live response; a bank has the undocumented attributes `blocked` and `destination` (always `0`, not read) |
| A posting rule line has a ratio | The ratio can be empty, for a line without fixed share |
| – | A posting rule can hold a line with nothing in it; it is left out |
| The VAT code is a code | It can be `#` (no VAT) |
| `<groups>`, `<postingrules>` and `<paymentconditions>` | Left out of the response when empty |

## Fixed assets

Fixed assets (dimension type `AST`, level 3) are the third dimension (`dim3`) on investment accounts. `$twinfield->fixedAssets()` has `search()`, `get()`, `find()`, `create()`, `update()`, `save()`, `delete()` and `activate()`.

```php
use SolveBeam\Twinfield\FixedAsset\FixedAssetField;
use SolveBeam\Twinfield\FixedAsset\FixedAssetInput;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;

$fixedAssets = $twinfield->fixedAssets();

foreach ($fixedAssets->search()->group('VEHICLES')->all() as $summary) {
    echo $summary->code, ' ', $summary->name, PHP_EOL;
}

// Without code(), Twinfield gives the next free code.
$asset = $fixedAssets->create(
    FixedAssetInput::create('Delivery van')
        ->method('5YVEH')
        ->costCenter('00000')
        ->purchaseDate(new DateTimeImmutable('2026-03-15'))
        ->depreciateFrom(FinancialPeriod::of(2026, 4))
        ->residualValue('2500.00')
        ->freeText(1, 'WDB9066331S123456')
        ->group('VEHICLES'),
);

echo $asset->assetStatus->name, PHP_EOL; // ToBeActivated

$fixedAssets->update($asset->edit()->residualValue('1000.00'));

$fixedAssets->delete($asset->code); // DeleteResult::Hidden when it is used in a transaction
```

### Read model

`FixedAsset` has the dimension fields (`code`, `name`, `uid`, `status`, `inUse`, `touched`, `modified`), the `costCenter` and `vatCode` from the financials, the `groups`, and the fixed asset settings:

| Property | Element | Notes |
|---|---|---|
| `assetStatus` | `status` | `FixedAssetStatus`: `ToBeActivated`, `Active`, `Inactive` or `Sold`. Not the same as `status`, which is the dimension status (active, deleted, hidden). |
| `method` | `method` | `AssetMethodReference` with code and name; null when the asset has no method. Read the method with `$twinfield->assetMethods()->get($asset->method->code)`. |
| `periods`, `periodsInherited` | `nrofperiods` | `periodsInherited` is true when the number comes from the asset method. |
| `percentage` | `percentage` | Numeric string such as `'12.5'`; null for methods without percentage. |
| `residualValue`, `stopValue` | `residualvalue`, `stopvalue` | `Amount`. |
| `depreciateFrom`, `lastDepreciation` | `beginperiod`, `lastdepreciation` | `FinancialPeriod`, also for weekly periods such as `2015/48`; null until set. |
| `purchaseDate`, `sellDate` | `purchasedate`, `selldate` | Null until set or sold. |
| `freeTexts`, `freeText(1)` | `freetext1`–`freetext5` | Values only; the asset method has the labels (`$method->freeText(1)->label`). |
| `transactionLines` | `translines` | `FixedAssetTransactionLine`: transaction type and number, line, `amount`, `period`, `account()` (dim1), `costCenter()` (dim2), `dimension(1–6)`. |
| `lockedFields`, `isLocked()` | `@locked` | What Twinfield does not allow to change now. |

### Locked settings

Twinfield locks settings depending on the status of the asset. The status and sell date are always locked; once an asset is active, the begin period, last depreciation and transaction lines are locked too. `edit()` leaves locked settings out, and setting one on that input throws a `LogicException` before anything is sent:

```php
$input = $asset->edit()->residualValue('1000.00');

if (!$asset->isLocked(FixedAssetField::DepreciateFrom)) {
    $input = $input->depreciateFrom(FinancialPeriod::of(2026, 5));
}
```

`edit()` also leaves out the number of periods when it comes from the asset method, so it keeps following the method.

### Status and transaction lines

The status, sell date and transaction lines cannot be sent. A new asset has the status `ToBeActivated`. Transaction lines are the transactions with the asset code as dimension 3, e.g. the purchase on the investment account; Twinfield links them when you book such a transaction. Activating, selling and disposing of assets are done in Twinfield itself.

### Differences with the documentation

Live responses differ from the documented ones, and the SDK follows the live responses:

- The asset status is lowercase (`tobeactivated`, `active`, `sold`), not `ToBeActivated`.
- Every element in `<fixedassets>` has `@locked`, also `<method>`. The documented save response leaves `@locked` out on the elements of the request; they are read as not locked.
- `<method>`, `<nrofperiods>`, `<beginperiod>`, `<purchasedate>` and `<lastdepreciation>` can be empty, and `<groups>` is left out when the asset is in no group.
- The cost center is always on level 2 (`substitutionlevel`), with the name and dimension type on `<substitutewith>`.

## General ledger accounts

General ledger accounts are two dimension types with the same settings: balance sheet accounts (`BAS`) and profit and loss accounts (`PNL`), both level 1, the first dimension (`dim1`) of every transaction line. Each type has its own resource, with `search()`, `get()`, `find()`, `create()`, `update()`, `save()`, `delete()` and `activate()`:

```php
use SolveBeam\Twinfield\GeneralLedgerAccount\ChildValidation;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountInput;
use SolveBeam\Twinfield\GeneralLedgerAccount\SubAnalysis;

$balanceSheet = $twinfield->balanceSheetAccounts();      // BAS
$profitAndLoss = $twinfield->profitAndLossAccounts();    // PNL

foreach ($profitAndLoss->search('8*')->inCode()->includeHidden()->all() as $summary) {
    echo $summary->code, ' ', $summary->name, $summary->hidden ? ' (hidden)' : '', PHP_EOL;
}

$account = $profitAndLoss->create(
    GeneralLedgerAccountInput::profitAndLoss('8010', 'Revenue services')
        ->shortName('Services')
        ->vatCode('VH')
        ->subAnalysis(SubAnalysis::Required)
        ->childValidation(ChildValidation::dimensionType('KPL'))
        ->group('REVENUE'),
);

$profitAndLoss->update($account->edit()->name('Revenue consultancy'));

$profitAndLoss->delete('8010'); // DeleteResult::Hidden when it is in use
```

The input knows its type: `GeneralLedgerAccountInput::balanceSheet()`, `::profitAndLoss()`, or `::of($type, …)` when the type is a variable. The resource of the other type refuses it with an `InvalidArgumentException` before anything is sent, so an account never ends up as the wrong type. A code belongs to one type: `$balanceSheet->find('8000')` is null when `8000` is a profit and loss account.

The code is required, as it is part of the chart of accounts (customers and fixed assets can get the next free code instead). The code format (mask) differs per company; see the [dimension types](#dimension-types).

### Read model

`GeneralLedgerAccount` has the dimension fields (`code`, `name`, `shortName`, `uid`, `status`, `inUse`, `behaviour`, `touched`, `modified`) and:

| Property | Element | Notes |
|---|---|---|
| `type` | `type` | `GeneralLedgerAccountType::BalanceSheet` or `ProfitAndLoss`; also `isBalanceSheet()` and `isProfitAndLoss()`. |
| `activeFrom`, `activeUntil` | `beginyear`/`beginperiod`, `endyear`/`endperiod` | `FinancialPeriod`; null for no limit (Twinfield sends 0). |
| `matchable` | `financials/matchtype` | True for `matchable`, e.g. a suspense account. |
| `subAnalysis` | `financials/subanalyse` | `SubAnalysis`, see below. |
| `childValidations` | `financials/childvalidations` | List of `ChildValidation`, see below. |
| `vatCode`, `vatCodeFixed` | `financials/vatcode` | `VatCodeReference` with `code`, `name`, `shortName` and `type` (purchase or sales); null when not set. `vatCodeFixed` is true when lines can only use this VAT code. |
| `groups`, `inGroup()` | `groups` | Dimension group codes. |

Twinfield documents the other elements (due days, pay code, e-invoicing, collect mandate, website, …) as "not in use" for general ledger accounts, so the SDK leaves them out. `financials/accounttype` (`balance` or `profitandloss`) follows from the type: it is checked when reading and not sent when saving.

### Sub-analysis and child validations

`subAnalysis` says whether lines on the account need a dimension 2, such as a cost center or customer:

| `SubAnalysis` | `subanalyse` | Meaning |
|---|---|---|
| `Required` | `true` | Every line needs a dimension 2. |
| `NotAllowed` | `false` | Lines have no dimension 2. |
| `Optional` | `maybe` | A dimension 2 is allowed; usual for profit and loss accounts. |

Child validations say which dimensions are allowed below the account, per financial level (2 for customers, suppliers and cost centers, 3 for projects and fixed assets, 4 for activities):

```php
ChildValidation::dimensionType('KPL');       // any cost center; the level follows from the type
ChildValidation::dimensionType('XYZ', 2);    // a type the SDK does not know: pass the level
ChildValidation::dimension('00100', 2);      // only cost center 00100
ChildValidation::group('MARKETING', 2);      // the dimensions in a dimension group
ChildValidation::group('MARKETING');         // level 0: groups only, any level
```

`childValidation()` adds one; `childValidations(...)` replaces them all, and without arguments removes them. Groups work the same with `group()` and `groups(...)`. `edit()` carries the current child validations and groups, so an update keeps what you do not change.

### Differences with the documentation

Live responses (read on two test companies, about 230 accounts) differ from the documented ones, and the SDK follows the live responses:

- `<childvalidations>` and `<groups>` are left out when they are empty.
- An account without VAT code has `<vatcode fixed="false" />`, without the `name`, `shortname` and `type` attributes.
- Hidden accounts have the status `hide`. The Finder leaves them out, unless you search with `includeHidden()`: the rows then have a third column with `active` or `hidden`.

## Projects

Projects (dimension type `PRJ`) have the same time & expenses settings as activities. In Twinfield Time & Expenses, hours are booked on an activity of a project; in the ledger, projects are dimensions at level 3 (activities at level 4). `$twinfield->projects()` has the same methods as activities: `search()`, `get()`, `find()`, `create()`, `update()`, `save()`, `delete()` and `activate()`.

```php
use SolveBeam\Twinfield\Dimension\ChangeMode;
use SolveBeam\Twinfield\Dimension\Quantity;
use SolveBeam\Twinfield\Project\ProjectInput;

$projects = $twinfield->projects();

$project = $projects->create(
    ProjectInput::create('P0100', 'Website redesign and API documentation')
        ->shortName('Website')
        ->customer('1010', ChangeMode::Disallow) // users may not book on another customer
        ->rate('T001')
        ->billable(true, forRatio: true)
        ->vatCode('VH')
        ->validFrom(new DateTimeImmutable('2026-01-01'))
        ->quantity(Quantity::create('KM')->rate('KM')),
);

$projects->update($project->edit()->validTill(new DateTimeImmutable('2026-12-31')));

echo $project->projects->customer->code, PHP_EOL; // 1010
```

What differs from activities:

- The **name** may be 80 characters (activities: 40). The short name is at most 20.
- The **VAT code** applies to all activities of the project; an activity with its own VAT code uses that one instead.
- Settings with `ChangeMode::Inherit` come from the **activity or user level**.
- Twinfield links every project to a **customer**: set it with `customer()`, or leave it inherited so the customer of the activity is used. The SDK does not enforce it.
- The default code format is `P[0-9][0-9][0-9][0-9]` (in Twinfield under dimension types, `PRJ`).

The read model `Project` has the same fields as `Activity`, with the time & expenses settings in `$project->projects` (a `ProjectSettings`). Use projects as `dim3` on transaction lines and per project in budgets: `$twinfield->budgets()->totals($code, 2026)->byProject()` (see [Budgets](#budgets)).

## Asset methods

An asset method says how fixed assets are depreciated and on which accounts. It is not a dimension, but `$twinfield->assetMethods()` has the same methods as the dimension resources: `search()`, `get()`, `find()`, `create()`, `update()`, `save()`, `delete()` and `activate()`. The differences:

- Search uses the Finder type `ASM` and gives `AssetMethodSummary` results. `group()` is for dimensions only.
- Twinfield has no `<read>` for asset methods. `get()` sends an `<assetmethod>` with only the company and code, which is how Twinfield reads one.
- Not found is an `AssetMethodNotFoundException`, an existing code on `create()` an `AssetMethodAlreadyExistsException`.
- `create()`, `update()` and `save()` read the asset method back after saving, so you always get the complete `AssetMethod`.
- The calculation is set with one method: `linear(60)`, `linearPercentage('20')`, `reducingBalance('25')` or `noDepreciation()`. Percentages are strings with a dot (`'12.5'`), never floats. Calculation settings that are not set are not sent, so Twinfield keeps them.

```php
use SolveBeam\Twinfield\AssetMethod\AssetMethodInput;
use SolveBeam\Twinfield\AssetMethod\DepreciationStart;

$assetMethods = $twinfield->assetMethods();

$method = $assetMethods->create(
    AssetMethodInput::create('LAPTOP', 'Laptops')
        ->linear(36)
        ->depreciateFrom(DepreciationStart::FromPurchaseDate)
        ->purchaseValueAccount('0501')
        ->cumulativeDepreciationAccount('0135')
        ->depreciationCostsAccount('4730')
        ->freeText(1, 'Serial number'),
);

$assetMethods->update($method->edit()->reducingBalance('25'));
```

## Bank books

Bank books are not dimensions and not part of the XML web services: `$twinfield->bankBooks()` uses the Command & Query web service `BankBookService`. It has `search()`, `get()`, `find()`, `create()` and `update()`, plus one method per change command. The differences:

- Search uses the Finder type `BNK` with `banktype=0`, so cash books are left out. Results are `BankBookSummary`s; `group()` is for dimensions only.
- Twinfield has **no delete** for bank books. Cash books are not bank books: `get('KAS')` throws `BankBookNotFoundException`. See [Cash books](#cash-books).
- There is no single save command. `update()` reads the bank book and sends only the commands for values that differ: `ChangeBankBookName`, `ChangeBankBookShortName`, `ChangeBankBookBankAccount` and `ChangeBankBookGeneralLedgerAccount`. So an unchanged account number or ledger account never runs into "may not be changed" once the bank book is in use. The commands are separate requests and not atomic.
- `rename()`, `changeShortName()`, `changeBankAccount()` and `changeGeneralLedgerAccount()` send one command.
- Not found is a `BankBookNotFoundException`, an existing code on `create()` a `BankBookAlreadyExistsException`. Other refusals are a `CommandQueryFaultException`; `BankBookServiceFaultCode::tryFrom($exception->errorCode)` tells which one.
- `BankAccountInput` removes spaces from the IBAN, writes it in capitals and checks the check digits; BIC and country code are checked too. The read model `BankAccount` takes the values as Twinfield returns them, with `null` for empty values.
- `automaticBankProcessing` (statements, payments, direct debits and the sign-up state) is read-only.

```php
use SolveBeam\Twinfield\BankBook\BankAccountInput;
use SolveBeam\Twinfield\BankBook\BankBookInput;

$bankBooks = $twinfield->bankBooks();

$bankBook = $bankBooks->create(
    BankBookInput::create('ABN', 'ABN AMRO')
        ->generalLedgerAccount('1120')
        ->bankAccount(BankAccountInput::create()->name('Example B.V.')->iban('NL91 ABNA 0417 1643 00')),
);

$bankBooks->update($bankBook->edit()->shortName('ABN'));
```

The [documentation page](https://developers.twinfield.com/documentation/api/master-data/bank-book/) differs from the service itself. The SDK follows the WSDL (`wsdl/accounting/BankBookService.svc.wsdl.xml`):

| Documentation page | WSDL |
|---|---|
| `BankAccount` is an array | One `BankAccount` |
| `CreateBankBook` has no short name | `CreateBankBook` has `ShortName` |
| Fault codes `BankBookInvalidStructure`, `BankBookInvalidBusinessLogic` | `BankBookNotFound`, `BankBookAlreadyExists`, `BankBookInvalidAccountSettings`, `BankBookInvalidGeneralLedgerAccount`, `BankBookAccountNumberMayNotBeChanged`, `BankBookGeneralLedgerAccountMayNotBeChanged` |
| `SignUpState` is an array | One value: `Inactive`, `Pending` or `Active` |

All `BankAccount` fields except `Country` are required in the WSDL (nillable), so empty ones are sent as `i:nil="true"`.

## Budgets

`$twinfield->budgets()` uses the Command & Query web service `BudgetService`. It can only **read**: budgets cannot be created or changed through the API.

- `search()` uses the Finder type `BDS` and returns `BudgetSummary`s (`code`, `name`).
- `totals($code, $year)` starts an immutable `BudgetTotalsQuery`. Every filter returns a new query; one of the three endings sends it.

| Method | What it does | Default |
|---|---|---|
| `period(3)`, `periods(1, 6)` | Periods, both included; 0 is the opening balance | 0–55 |
| `generalLedgerAccounts('8000', '8999')`, `generalLedgerAccount('8000')` | General ledger accounts (dimension 1) | All |
| `dimension2('KPL100', 'KPL199')`, `dimension2('KPL100')` | Dimension 2, e.g. cost centers | All |
| `dimension3('P0001', 'P0099')`, `dimension3('P0001')` | Dimension 3, e.g. projects | All |
| `finalOnly()`, `provisionalOnly()`, `finalAndProvisional()` | Which transactions count for the actual amounts | Both |
| `regime(Regime::Fiscal)` | Reporting regime, for companies with more than one | Company default |
| `byProfitAndLoss()` | Sends `GetBudgetByProfitAndLoss`: per account and period | |
| `byCostCenter()` | Sends `GetBudgetByCostCenter`: also per dimension 2 | |
| `byProject()` | Sends `GetBudgetByProject`: also per dimensions 2 and 3 | |

```php
$totals = $twinfield->budgets()->totals('001', 2025)
    ->periods(1, 6)
    ->generalLedgerAccounts('4000', '4999')
    ->finalOnly()
    ->byCostCenter();

foreach ($totals as $total) {
    echo $total->period, ' ', $total->generalLedgerAccount, ' ', $total->dimension2 ?? '-', ' ',
        $total->budget->total, ' ', $total->actual->total, PHP_EOL;
}
```

The result is `BudgetTotals`: countable and iterable, with `forPeriod()`, `forGeneralLedgerAccount()`, `forDimension2()` and `forDimension3()` to filter, `periods()` and `generalLedgerAccounts()` for the distinct values, and `isEmpty()`. Twinfield only returns lines with a budget or actual amount. Each `BudgetTotal` has:

- `year`, `period` and `generalLedgerAccount`;
- `groups`: the group codes of the account from the highest level down, e.g. `['3', 'RES40', 'KST400']`; empty when the account is in no group;
- `dimension2` and `dimension3`: `null` for amounts without that dimension. Dimension 2 is the cost center on profit and loss accounts, but the customer or supplier on balance sheet accounts;
- `budget` and `actual`, each with `debit`, `credit` and `total` (debit minus credit).

Amounts are **exact decimal strings** as Twinfield returns them, e.g. `2100.5000`, `-318.0200` or `0`, never floats. Calculate with [bcmath](https://www.php.net/bcmath) (`bcadd($a, $b, 2)`) or a money library.

An unknown budget code throws `BudgetNotFoundException`. The query checks its input itself (code, four-digit year, periods 0–55 in order, at least final or provisional), so the other Twinfield refusals (`BudgetServiceFaultCode`) do not occur.

The [documentation page](https://developers.twinfield.com/documentation/api/master-data/budget/) differs from the service itself:

| Documentation page | Service |
|---|---|
| Only `SecurityFault` and `ValidationFault` | Also `BudgetServiceFault` with `BudgetNotFound`, `CodeMandatory`, `YearMandatory` and `ChooseAtLeastOneTransactionStatus` |
| No `Regime` field | `Regime` (`Generic`, `Fiscal`, `Commercial`, `Economic`) in the WSDL |
| Periods look optional | Without `PeriodFrom`/`PeriodTo` the result is empty, so the SDK always sends them |

## Cash books

Cash books work like bank books, with their own Command & Query web service `CashBookService`: `$twinfield->cashBooks()` has `search()`, `get()`, `find()`, `create()` and `update()`, plus `rename()`, `changeShortName()` and `changeGeneralLedgerAccount()`. A cash book has only a name, a short name and a general ledger account; there is no bank account and no automatic processing.

- Search uses the Finder type `BNK` with `banktype=1`, so bank books are left out. Results are `CashBookSummary`s.
- Twinfield has **no delete** for cash books. Bank books are not cash books: `get('BNK')` throws `CashBookNotFoundException`, and `find()` returns `null`.
- `update()` reads the cash book and sends only the commands for values that differ: `ChangeCashBookName`, `ChangeCashBookShortName` and `ChangeCashBookGeneralLedgerAccount`. The commands are separate requests and not atomic.
- Not found is a `CashBookNotFoundException`, an existing code on `create()` a `CashBookAlreadyExistsException`. Other refusals are a `CommandQueryFaultException`; `CashBookServiceFaultCode::tryFrom($exception->errorCode)` tells which one (`InvalidGeneralLedgerAccount`, `GeneralLedgerAccountMayNotBeChanged`).
- Empty short name and ledger account are `null` in the read model `CashBook`. On the input, `null` or `''` removes them.
- To book on a cash book, pass it to `CashTransactionInput::forCashBook($cashBook, $date)` and create the transaction with `$twinfield->cashTransactions()` (see the [transactions guide](/guides/transactions/)).

```php
use SolveBeam\Twinfield\CashBook\CashBookInput;

$cashBooks = $twinfield->cashBooks();

$cashBook = $cashBooks->create(
    CashBookInput::create('KAS2', 'Kas winkel')
        ->shortName('Winkel')
        ->generalLedgerAccount('1010'),
);

$cashBooks->update($cashBook->edit()->name('Kas hoofdkantoor'));
```

The [documentation page](https://developers.twinfield.com/documentation/api/master-data/cash-book/) differs from the service itself. The SDK follows the WSDL (`wsdl/accounting/CashBookService.svc.wsdl.xml`):

| Documentation page | WSDL |
|---|---|
| `CreateCashBook` has no short name | `CreateCashBook` has `ShortName` |
| Fault codes `CashBookInvalidStructure`, `CashBookInvalidBusinessLogic` | `CashBookNotFound`, `CashBookAlreadyExists`, `CashBookInvalidGeneralLedgerAccount`, `CashBookGeneralLedgerAccountMayNotBeChanged` |
| `GetCashBook` sample sends `CompanyCompany` | The usual `CompanyCode` in the authentication header |

## Currencies

`$twinfield->currencies()` manages the currencies of the company and their exchange rates. It has `search()`, `exists()`, `get()`, `find()`, `create()`, `update()`, `save()` and `delete()`.

```php
use SolveBeam\Twinfield\Currency\CurrencyInput;

$currencies = $twinfield->currencies();

// Search (Finder type CUR): code and name
foreach ($currencies->search()->all() as $summary) {
    echo $summary->code, ' ', $summary->name, PHP_EOL;
}

// Read, with the rate on a date
$usd = $currencies->get('USD');
echo $usd->rateOn(new DateTimeImmutable('2026-03-15'))?->rate, PHP_EOL;

// Create with rates
$currencies->create(
    CurrencyInput::create('GBP', 'Pound sterling')
        ->shortName('£')
        ->rate(new DateTimeImmutable('2026-01-01'), '0.8650'),
);

// Add, change and remove rates
$currencies->update(
    $usd->edit()
        ->rate(new DateTimeImmutable('2026-07-01'), '1.0912')
        ->removeRate(new DateTimeImmutable('2025-01-01')),
);

// Delete
$currencies->delete('GBP');
```

- A `Currency` has `name`, `shortName` (`null` when not set), `status` and `rates`: `CurrencyRate`s with a `startDate` and a `rate`, oldest first. A rate applies from its start date until the next one. `rateOn($date)` gives the rate on a date, `latestRate()` the newest; both are `null` when there is none.
- Exchange rates are `ExchangeRate` objects: positive numeric strings with all their decimals (`'1.0850'` becomes `1.085`), never floats. Pass a string or an `ExchangeRate` to `rate()`. They are not `Amount`s: a rate is not money.
- `CurrencyInput` is immutable. `rate($startDate, $rate)` adds a rate or replaces the one with the same start date; `removeRate($startDate)` deletes it (`rate/@status="deleted"`).
- `$currency->edit()` has all current rates, so `update()` sends them again and only the rates you change differ.
- Not found is a `CurrencyNotFoundException`, an existing code on `create()` a `CurrencyAlreadyExistsException`. Other refusals are a `ProcessXmlException` with the messages per element.

Twinfield has no `<read>` for currencies, and a `<currency>` request creates the currency when the code is unknown. So `get()`, `update()` and `delete()` first check in the Finder that the code exists, and `get()` then sends a `<currency>` with only the company and the code, which returns the currency. A read never creates a currency, at the cost of one extra (small) request. `create()` and `save()` read the currency back after saving, so you always get all its rates.

## Dimension groups

A dimension group is a named set of dimensions, e.g. your key account customers or the cost centers of one department. `$twinfield->dimensionGroups()` has `search()`, `exists()`, `get()`, `find()`, `create()`, `update()`, `save()` and `delete()`.

```php
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupInput;

$groups = $twinfield->dimensionGroups();

// Search (Finder type GRP), optionally only groups with dimensions of one type
foreach ($groups->search()->dimensionType('DEB')->all() as $summary) {
    echo $summary->code, ' ', $summary->name, PHP_EOL;
}

// Read
$group = $groups->get('KEY-ACCOUNTS');

foreach ($group->dimensions as $dimension) {
    echo $dimension->type, ' ', $dimension->code, ' ', $dimension->name, PHP_EOL;
}

// Create
$groups->create(
    DimensionGroupInput::create('KEY-ACCOUNTS', 'Key accounts')
        ->shortName('Key')
        ->add('DEB', '1000', '1001', '1002'),
);

// Add and remove dimensions
$groups->update($group->edit()->add('DEB', '1003')->remove('DEB', '1000'));

// Delete
$groups->delete('KEY-ACCOUNTS');
```

- A `DimensionGroup` has `name`, `shortName` (`null` when not set), `status`, `touched` and `dimensions`: `DimensionGroupDimension`s with `type`, `code` and the `name` Twinfield adds. `has('DEB', '1000')`, `codes('DEB')` and `types()` help to look into them.
- A group can have dimensions of more than one type, e.g. customers and cost centers. `search()->dimensionType('KPL')` finds the groups that have at least one dimension of that type.
- `DimensionGroupInput` is immutable and is the **complete** group: saving it replaces the name, the short name and all dimensions. `add($type, ...$codes)` adds dimensions (each once), `remove($type, ...$codes)` and `clear(?$type)` remove them. Start from `$group->edit()` to keep what you do not change.
- The name has at most 40 characters, the short name at most 20. The dimensions must exist: Twinfield refuses an unknown code with a `ProcessXmlException` with the message on that dimension, e.g. `dimensiongroup/dimensions/dimension[2]/code`.
- Not found is a `DimensionGroupNotFoundException`, an existing code on `create()` a `DimensionGroupAlreadyExistsException`.
- `delete()` first empties the group and then sets its status to `deleted`. Twinfield keeps the code of a deleted group on its dimensions (e.g. in `$customer->groups`); emptying it first removes it there. The dimensions do not change otherwise. A deleted group is not in the Finder, so `get()` and `exists()` treat it as unknown, and `create()` can use its code again; that does not bring back the old dimensions.
- The dimensions of a group are in `$group->dimensions`.

Twinfield has no `<read>` for dimension groups, and a `<dimensiongroup>` request for an unknown code creates the group (named after its code), also when it only reads or deletes. So `get()`, `update()` and `delete()` first check in the Finder that the code exists, and `get()` then sends a `<dimensiongroup>` with only the company and the code, which returns the group. A read never creates a group, at the cost of one extra (small) request. Requests that save always send `status="active"`: without it, saving a deleted group keeps it deleted.

## Dimension types

`$twinfield->dimensionTypes()` reads and changes the settings of the dimension types (`BAS`, `PNL`, `CRD`, `DEB`, `KPL`, `AST`, `PRJ`, `ACT`): the name, the code mask and the labels of the address fields. It has `search()`, `exists()`, `get()`, `find()` and `update()`. Twinfield has a fixed set of types, so they cannot be created or deleted.

```php
use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;

$types = $twinfield->dimensionTypes();

// Search (Finder type DMT): code and name
foreach ($types->search()->all() as $summary) {
    echo $summary->code, ' ', $summary->name, PHP_EOL;
}

$types->search()->financialLevel(2)->all();    // CRD, DEB, KPL
$types->search()->ofDimension('1000')->all();  // the types with a dimension 1000, e.g. BAS and DEB

// Read
$customers = $types->get(DimensionTypeCode::AccountsReceivable);   // or get('DEB')

echo $customers->mask, PHP_EOL;                // e.g. 1[0-9][0-9][0-9]
$customers->mask->matches('1010');             // true: a valid code for a new customer
echo $customers->addressLabels->field4, PHP_EOL;

// Update the name, mask and address labels
$types->update(
    $customers->edit()
        ->mask('1[0-9][0-9][0-9][0-9]')
        ->addressLabel(6, 'IBAN'),
);
```

- A `DimensionType` has `name`, `shortName` (`null` when not set), `mask`, `levels`, `addressLabels` and `touched` (the number of changes). The short name and the levels are read-only.
- The `mask` is a `CodeMask` with the `pattern` as Twinfield returns it, e.g. `1[0-9][0-9][0-9]`, `P[0-9][0-9][0-9][0-9]` or `*` for any code. `matches($code)` understands `[…]` (a character from a set or range), `*` (anything), `?` (one character) and `#` (one digit); every other character stands for itself.
- `levels` is a `DimensionTypeLevels` with the `financial` level (1 general ledger, 2 customers, suppliers and cost centers, 3 projects and fixed assets, 4 activities), the level in `time` & expenses and in `fixedAssets`. A transaction line has a dimension per financial level.
- `addressLabels` names the free fields `field1`–`field6` of the addresses of customers and suppliers (`Address::$field1` …), `null` when a field is not used. `forField(4)` gives one label, `used()` only those that are set.
- `DimensionTypeInput` is immutable and comes from `$type->edit()`, so an update always sends all values. `name()` (40 characters), `mask()` (80) and `addressLabel($field, $label)` (20; `null` removes it) are checked before anything is sent.
- Not found is a `DimensionTypeNotFoundException`. Other refusals are a `ProcessXmlException` with the messages per element.

Twinfield has no `<read>` for dimension types: `get()` sends a `<dimensiontype>` with only the company and the code, which returns the type without changing it. An unknown code is refused, so a read needs only one request. Twinfield's documentation shows the mask as `1####`; companies return masks like `1[0-9][0-9][0-9]`.

## Rates

A rate is the price of an hour or of a quantity such as a kilometre in Twinfield Time & Expenses: the internal rate (cost price) and the external rate (selling price) per unit, per date range. Activities, projects and their quantities refer to a rate by its code, e.g. `$activity->projects->rate->code`. Rates are no dimensions, but `$twinfield->rates()` has the same methods as the dimension resources: `search()`, `get()`, `find()`, `create()`, `update()`, `save()`, `delete()` and `activate()`.

```php
use SolveBeam\Twinfield\Rate\RateInput;
use SolveBeam\Twinfield\Rate\RateType;

$rates = $twinfield->rates();

// Search: Finder type TRT, only rates for hours
foreach ($rates->search()->rateType(RateType::Time)->all() as $summary) {
    echo $summary->code, ' ', $summary->name, PHP_EOL;
}

// Read
$rate = $rates->get('CONSULT');   // or find(): null when it does not exist
echo $rate->priceOn(new DateTimeImmutable('2026-03-15'))?->external, ' ', $rate->currency, PHP_EOL;

// Create
$rates->create(
    RateInput::quantity('KM', 'Kilometres')
        ->currency('EUR')
        ->price(new DateTimeImmutable('2026-01-01'), internal: '0.15', external: '0.23'),
);

// New prices from July: the current price ends on June 30
$rates->update($rate->edit()->newPriceFrom(new DateTimeImmutable('2026-07-01'), '60', '105'));

// Delete: DeleteResult::Hidden when the rate is in use
$rates->delete('KM');
```

- `search()` gives `RateSummary` results with code and name. `rateType(RateType::Time)` or `rateType(RateType::Quantity)` keeps one type (Finder option `ratetype`).
- `get()` sends a `<read>` of type `projectrate`. Not found is a `RateNotFoundException`, an existing code on `create()` a `RateAlreadyExistsException`. `create()`, `update()` and `save()` read the rate back after saving, so you always get the complete `Rate`.
- A `Rate` has `type`, `unit` (the number of units the prices are for), `currency`, `inUse` and `prices`: `RatePrice` lines sorted by start date, each with its line `id`, `from`, `until` (null without end date), `internal` and `external`. `priceOn($date)` gives the line that covers the date, `latestPrice()` the line with the latest start date.
- Prices are `UnitPrice` objects: numeric strings with four decimals, as Twinfield keeps them (`'0.1550'`), never floats.
- `edit()` keeps the line ids, so an update changes the existing lines instead of adding them again. `price()` adds a line or changes the line with the same start date, `newPriceFrom()` ends the open line the day before and adds a new open line, and `removePrice($from)` deletes a line. The SDK refuses lines that overlap or end before they start, and an input without lines (Twinfield requires at least one).

### Differences with the documentation

The [documentation](https://developers.twinfield.com/documentation/api/master-data/rates/) is short. The read model is based on live responses:

- A line without end date has `enddate` 2999-12-31. The SDK reads that as `until` null and sends it for an open line.
- Prices have four decimals (`492.0000`).
- The read response has `inuse` on `projectrate`, and a `user` with name. The documented save response does not, so the SDK reads the rate back.
- Not found is `<projectrate msgtype="error" msg="…" result="0">` with only office and code.
- The documentation says `unit` is `1` for a price per hour. Live time rates have units such as `93`, `120` and `882`, so the unit may be in minutes. The SDK returns the number as Twinfield sends it.
- `TRT` is the Finder type for rates; transaction types are `TRS`.

## Offices

`$twinfield->offices()` lists, searches and reads the offices (companies, administrations) the user has access to. Offices are read-only in the API: they cannot be created, changed or deleted. Twinfield is moving from the term "office" to "company"; the classes keep the name `Office` for now.

```php
use SolveBeam\Twinfield\Office\OfficeField;

$offices = $twinfield->offices();

// List (Finder type OFF, all pages): code, name, company ID, country, VAT period
foreach ($offices->list() as $summary) {
    echo $summary->code, ' ', $summary->name, ' ', $summary->companyId, PHP_EOL;
}

// Search
$offices->search('*Holding*')->inName()->all();
$offices->search()->withoutTaxGroup()->count();   // offices that can join a fiscal unity

// Read
$office = $offices->get('1000');                  // or find(): null when it does not exist
$office = $twinfield->company('1000')->offices()->current();

echo $office->general->baseCurrency->code, PHP_EOL;              // EUR
echo $office->systemDimensions->accountsReceivable?->code, PHP_EOL;
echo $office->vatManagement->taxGroup?->code ?? 'no fiscal unity', PHP_EOL;
$office->isLocked(OfficeField::DeclarationTimeframe);

// A client for the office
$company = $twinfield->company($office);           // also takes an OfficeSummary
```

- `list()` and `search()` return `OfficeSummary` objects with `code`, `name`, `companyId` (the GUID of the company), `country`, `vatPeriod` and `startingQuarter`. Searches can match `inCode()`, `inName()` or `inCodeAndName()` (default). `withoutTaxGroup()` keeps only offices that are not in a fiscal unity, are no template and have no VAT declaration in progress.
- `get()` returns an `Office`, `find()` returns null instead of throwing `OfficeNotFoundException` for an unknown code or an office the user has no access to. `current()` reads the office of the client and throws a `LogicException` when the client has no company.
- `get()` also works on a client without company: Twinfield only reads an office with a company in the request, so the SDK uses the office itself.

### Read model

An `Office` has `code`, `name`, `shortName`, `status`, `created`, `modified`, `touched`, the `user` who last changed it, and a section per part of the settings:

| Property | Class | Contents |
|---|---|---|
| `general` | `OfficeGeneral` | Base and reporting currency, type, demo, VAT and chamber of commerce numbers, last closed year, SEPA creditor identifier, default bank book and matching, hierarchy, template, payment discount, `address` (`OfficeAddress`) |
| `systemDimensions` | `SystemDimensions` | Control accounts for customers and suppliers, suspense account, turnover, exchange and payment differences, discount, the default cost center of fixed assets, opening balance and closing account. Each is a `SystemDimension` (code, name, `readOnly`), or null when not set |
| `systemDimensionTypes` | `SystemDimensionTypes` | The dimension type codes per purpose: `DEB`, `CRD`, `BAS`, `PNL`, `AST`, `PRJ`, `KPL`, `ACT` |
| `creditManagement` | `CreditManagement` | Days after the due date and after a part payment, the minimum amount (`Amount`), browses, prompts and sort fields |
| `vatManagement` | `VatManagement` | Contacts of the taxpayer and intermediary, tax group (fiscal unity), declaration and ICP periods, starting quarter, which provisional transactions the declaration includes, default gateway, accounting scheme |
| `fixedAssets` | `FixedAssetSettings` | The depreciation transaction type and browses |
| `intercompany` | `Intercompany` | Status and the `outgoing` and `incoming` relations with other offices (`IntercompanyRelation`) |
| `regional` | `RegionalSettings` | Date format and separators in the Twinfield user interface |

Codes with a name, such as currencies, bank books and the tax group, are `NamedReference` objects with `code`, `name` and `shortName`. `lockedFields` lists the settings with `locked="true"`, e.g. the VAT period once declarations were filed; `isLocked()` checks one.

Optional settings are null when Twinfield returns an empty element. Twinfield writes "not set" as `0` for `lastClosedYear` and `sicMajorGroup`; the SDK turns that into null. Unknown values, such as a new office type, throw an `UnexpectedResponseException`.

### Differences with the documentation

The [documentation](https://developers.twinfield.com/documentation/api/master-data/offices) shows the requests, not the responses. The read model is based on the responses of 247 live offices:

- A read without company in the header is refused with SOAP fault 359 (company missing). With an unknown office as company, Twinfield refuses the same way; with another company in the header, an unknown office is `<office msgtype="error" msg="…" result="0">` with only its code.
- With `includeid=1` the Finder inserts the company ID as third column: code, name, ID, country, VAT period, starting quarter.
- The VAT booleans `include…provisionaltransactions` and `vatrollover` are `True` and `False`, unlike the rest of the response (`true`, `false`). `mayestimate` is `0` or `1`.
- Outgoing intercompany relations have an `indirectoffice`, incoming ones a `transaction` instead. The name of the other office is empty when the user has no access to it.
- The values of the declaration and ICP timeframes are not documented. Live offices have `0`, `1` (most) or `2`, and a starting quarter of `21` or `30`; the SDK returns them as numbers.

## Users

`$twinfield->users()` searches, reads, creates, updates and deletes the users of the organisation: who may log in, with which role, and to which companies. `current()` reads the user the access token belongs to.

```php
use SolveBeam\Twinfield\User\UserCulture;
use SolveBeam\Twinfield\User\UserDeleteReason;
use SolveBeam\Twinfield\User\UserInput;
use SolveBeam\Twinfield\User\UserType;

$users = $twinfield->users();

// List and search (Finder type USR)
foreach ($users->list() as $summary) {
    echo $summary->code, ' ', $summary->name, PHP_EOL;
}

$users->search('*_AI')->inCode()->get();
$users->search()->withMutualCompanies()->count();   // users that share a company with you

// The user of the access token
echo $twinfield->userCode(), PHP_EOL;              // no request
$me = $users->current();

// Read
$user = $users->get('JDOE');                       // or find(): null when it does not exist
$user->hasAccessTo('1000');
$user->account->isDisabledAt(new DateTimeImmutable());

// Create and invite (can cost money: Twinfield charges per user)
$users->create(
    UserInput::create('JSMITH', 'Jane Smith')
        ->email('jane.smith@example.com')
        ->type(UserType::Regular)
        ->culture(UserCulture::Dutch)
        ->role('LVL1')
        ->companies('1000', '1001')
        ->invite(),
);

// Update
$users->update($user->edit()->weekHours(32)->grantAccessTo('1002'));

// Delete: DeleteResult::Hidden when the user is used in transactions
$users->delete('JSMITH', UserDeleteReason::NoLongerOperative, 'Left the company');
```

- Users belong to the organisation, not to a company, but Twinfield refuses user requests without a company in the header (SOAP fault 359). Any company works; the SDK throws a `LogicException` before sending when the client has none.
- `search()` gives `UserSummary` results with code and name. `withMutualCompanies()` (option `mutualoffices=1`) and `withoutAccessRules()` (option `accessrules=0`, only with access to user management) widen the search.
- Not found is a `UserNotFoundException`, an existing code on `create()` a `UserAlreadyExistsException`. `create()`, `update()` and `save()` read the user back after saving.
- A `User` has `role` (`UserRole` with code, name and level), `type`, `culture`, `invitationStatus`, `companies` (`CompanyAccess`), `account` (`UserAccount`: locked, last logon, single sign-on, password expiry, disabled period), `smsLogin`, `time`, `expenseDefaults`, `federation` and who changed it last. The password is never returned.
- A user is either `restricted` to the listed companies, or has access to all companies of the organisation with an empty list.
- `UserInput` sends only what you set. `edit()` leaves out what Twinfield locks or what would change access by accident: the password, the invitation status, `demo` for users that are no client of an accountant, the password expiry with single sign-on, and the company list of users with access to all companies. `grantAccessTo()` and `revokeAccessTo()` therefore need a company list, and throw a `LogicException` without one.
- Inconsistent input throws before sending: `invite()` without `culture()` and `role()`, `demo()` for another type than `UserType::AccountantCustomer`, or a password expiry date with single sign-on.

### The user of the access token

The access token validation already says which user the token belongs to (`twf.organisationUserCode`). `AccessTokenProvider::userCode()` exposes it, so the SDK does not ask for it again:

- `RefreshTokenAccessTokenProvider` takes it from the validation it calls anyway to find the cluster URL, and caches it with the access token.
- `StaticAccessTokenProvider` takes it as third argument: `new StaticAccessTokenProvider($token, $validation->clusterUrl, $validation->organisationUserCode)`. Without it, `userCode()` and `users()->current()` throw a `LogicException`.

Your own `AccessTokenProvider` implementation needs a `userCode()` method too.

### Differences with the documentation

The read model is based on the responses of all 18 users of a live organisation (9 November 2026). Documentation: [Users](https://developers.twinfield.com/documentation/api/master-data/users/).

- Reads and Finder searches without company are refused with SOAP fault 359, although users are not part of a company.
- `restricted` in `<office>` is documented as "not in use". Live it is `false` with an empty `<offices>` for users with access to all companies, and `true` with the list for the others.
- `templatecompanyaccess` is returned in lower case (`nouse`, `useandmanage`), not as documented (`NoUse`, `UseAndManage`). The SDK reads both and sends lower case.
- The response has `acceptExtraCost` in camel case; the documentation writes `acceptextracost`, which the SDK sends. It is not part of the read model: it only matters when changing the role.
- Not in the documentation: `wkemailaddress` (the email address of the Wolters Kluwer account, read as `accountEmail`), `account/expireperiod` (always `0`), `invitationstatus/@CurrentInvitationStatus` (same as the text), and `touched`, `created` and `modified` a second time at the end.
- `support` ("not in use") is `none` or `primary`, and `@level` on `<user>` is `Supervisor` for every user. Their meaning is unknown, so the SDK does not read them.
- `<password>` and `<federation>` are never returned: federation is only enabled on request.
- The Finder returns two columns, code and name. With `mutualoffices=1` it returned 31 users instead of 18; the extra users can be read, so the default search leaves out users of the organisation.
- Not found is `<user status="default" msgtype="error" msg="…" result="0">` with only the code.

Creating, updating and deleting are not yet verified live, see [`TODO.md`](https://github.com/solvebeam/php-twinfield/blob/main/TODO.md).

## Examples

- [List activities](/examples/activities/list-activities/)
- [Search activities](/examples/activities/search-activities/)
- [Read activity](/examples/activities/read-activity/)
- [Create activity](/examples/activities/create-activity/)
- [Update activity](/examples/activities/update-activity/)
- [Delete activity](/examples/activities/delete-activity/)
- [List asset methods](/examples/asset-methods/list-asset-methods/)
- [Read asset method](/examples/asset-methods/read-asset-method/)
- [Create asset method](/examples/asset-methods/create-asset-method/)
- [Update asset method](/examples/asset-methods/update-asset-method/)
- [Delete asset method](/examples/asset-methods/delete-asset-method/)
- [List bank books](/examples/bank-books/list-bank-books/)
- [Read bank book](/examples/bank-books/read-bank-book/)
- [Create bank book](/examples/bank-books/create-bank-book/)
- [Update bank book](/examples/bank-books/update-bank-book/)
- [List budgets](/examples/budgets/list-budgets/)
- [Budget vs. actual](/examples/budgets/budget-vs-actual/)
- [Budget by cost center](/examples/budgets/budget-by-cost-center/)
- [Budget by project](/examples/budgets/budget-by-project/)
- [List cash books](/examples/cash-books/list-cash-books/)
- [Read cash book](/examples/cash-books/read-cash-book/)
- [Create cash book](/examples/cash-books/create-cash-book/)
- [Update cash book](/examples/cash-books/update-cash-book/)
- [List currencies](/examples/currencies/list-currencies/)
- [Read currency](/examples/currencies/read-currency/)
- [Create currency](/examples/currencies/create-currency/)
- [Update currency rates](/examples/currencies/update-currency-rates/)
- [Delete currency](/examples/currencies/delete-currency/)
- [List dimension groups](/examples/dimension-groups/list-dimension-groups/)
- [Read dimension group](/examples/dimension-groups/read-dimension-group/)
- [Create dimension group](/examples/dimension-groups/create-dimension-group/)
- [Update dimension group](/examples/dimension-groups/update-dimension-group/)
- [Delete dimension group](/examples/dimension-groups/delete-dimension-group/)
- [List dimension types](/examples/dimension-types/list-dimension-types/)
- [Read dimension type](/examples/dimension-types/read-dimension-type/)
- [Update dimension type](/examples/dimension-types/update-dimension-type/)
- [List fixed assets](/examples/fixed-assets/list-fixed-assets/)
- [Read fixed asset](/examples/fixed-assets/read-fixed-asset/)
- [Create fixed asset](/examples/fixed-assets/create-fixed-asset/)
- [Update fixed asset](/examples/fixed-assets/update-fixed-asset/)
- [Delete fixed asset](/examples/fixed-assets/delete-fixed-asset/)
- [List general ledger accounts](/examples/general-ledger-accounts/list-general-ledger-accounts/)
- [Read general ledger account](/examples/general-ledger-accounts/read-general-ledger-account/)
- [Create general ledger account](/examples/general-ledger-accounts/create-general-ledger-account/)
- [Update general ledger account](/examples/general-ledger-accounts/update-general-ledger-account/)
- [Delete general ledger account](/examples/general-ledger-accounts/delete-general-ledger-account/)
- [List projects](/examples/projects/list-projects/)
- [Search projects](/examples/projects/search-projects/)
- [Read project](/examples/projects/read-project/)
- [Create project](/examples/projects/create-project/)
- [Update project](/examples/projects/update-project/)
- [Delete project](/examples/projects/delete-project/)
- [List rates](/examples/rates/list-rates/)
- [Search rates](/examples/rates/search-rates/)
- [Read rate](/examples/rates/read-rate/)
- [Create rate](/examples/rates/create-rate/)
- [Update rate price](/examples/rates/update-rate-price/)
- [Delete rate](/examples/rates/delete-rate/)
- [List offices](/examples/offices/list-offices/)
- [Search offices](/examples/offices/search-offices/)
- [Read office](/examples/offices/read-office/)
- [Switch company](/examples/offices/switch-company/)
- [List customers](/examples/customers/list-customers/)
- [Read customer](/examples/customers/read-customer/)
- [Create customer](/examples/customers/create-customer/)
- [Update customer](/examples/customers/update-customer/)
- [Delete customer](/examples/customers/delete-customer/)
- [List suppliers](/examples/suppliers/list-suppliers/)
- [Read supplier](/examples/suppliers/read-supplier/)
- [Create supplier](/examples/suppliers/create-supplier/)
- [Update supplier](/examples/suppliers/update-supplier/)
- [Delete supplier](/examples/suppliers/delete-supplier/)
- [List users](/examples/users/list-users/)
- [Search users](/examples/users/search-users/)
- [Read user](/examples/users/read-user/)
- [Current user](/examples/users/current-user/)
- [Create user](/examples/users/create-user/)
- [Update user](/examples/users/update-user/)
- [Delete user](/examples/users/delete-user/)
