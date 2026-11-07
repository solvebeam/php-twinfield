---
title: Master data (dimensions)
description: Search, read, create, update and delete master data such as customers, activities and cost centers, with typed models and clear errors.
---

Master data in Twinfield, such as activities, projects, customers and suppliers, are **dimensions**. Each has a dimension type, e.g. `ACT` for activities. The SDK searches them with the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) and reads and writes them with the [XML web services](/guides/process-xml/). You work with typed objects and never write XML yourself.

| Dimension type | Resource | Status |
|---|---|---|
| Activities (`ACT`) | `$twinfield->activities()` | Search, read, create, update, delete, activate |
| [Cost centers](#cost-centers) (`KPL`) | `$twinfield->costCenters()` | Search, read, create, update, delete, activate |
| [Customers](#customers) (`DEB`) | `$twinfield->customers()` | Search (also by bank account and address), read, create, update, delete, activate |
| Projects (`PRJ`), suppliers (`CRD`), … | – | Not yet; use [`processXml()`](/guides/process-xml/) |

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

Time & expenses settings such as the customer, rate, authoriser and billable have a `ChangeMode`: whether users may change the value in their timesheet, or whether it comes from the project or user level.

| ChangeMode | Meaning | XML |
|---|---|---|
| `Allow` | Fixed value, users may change it | `locked="false" inherit="false"` |
| `Disallow` | Fixed value, users may not change it | `locked="true" inherit="false"` |
| `Inherit` | From the project or user level | `locked="true" inherit="true"` |

New activities inherit every setting until you set it. Inherited settings have no value of their own, so `code` (and `billable`) are `null`.

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
| `banks` | `Bank`s: `iban`, `bic`, `accountNumber`, `ascription`, `bankName`, `nationalBankCode`, the bank address and `default` |
| `groups`, `postingRules`, `paymentConditions`, `discountArticle` | Dimension groups, posting rules with lines, payment discounts, the discount article |

- Optional values are `null` when they are not set; required values (such as the balance sheet account) are never `null`, and an unexpected response throws an `UnexpectedResponseException`.
- `defaultAddress()`, `defaultBank()`, `address($id)`, `bank($id)`, `addressesOfType($type)`, `vatNumber()` and `chamberOfCommerceNumber()` are shortcuts.
- The labels of the address fields are set per dimension type. By default field 1 is "attention of", 2 and 3 are address lines, 4 is the VAT number (only valid VAT numbers are accepted) and 5 the chamber of commerce number. `AddressInput` has `vatNumber()` and `chamberOfCommerceNumber()` for fields 4 and 5.
- `CustomerInput` is immutable and only sends what you set: when creating, everything else gets the default of the dimension type; when updating, it stays as it is. Setting a value to `null` clears it.
- Settings that belong together are set together, so they cannot contradict each other: `directDebit($payCode)` also sets the means of payment to a payment file, `withoutDirectDebit()` to none; `sendReminder(SendReminder::ByEmail, $email)` requires the email address.
- Addresses, bank accounts, groups and payment conditions are sent as a whole once set. `address()` and `bank()` replace the entry with the same id, or add one; a new default makes the others non-default. `withoutAddress($id)` and `withoutBank($id)` remove one; `withoutPostingRule($id)` deletes a posting rule (`status="deleted"`).
- IBANs are normalised (spaces removed, upper case) and checked with the ISO 13616 checksum. Amounts are numeric strings or `Amount`s, never floats.
- `Address`, `Bank`, `PostingRule` and `PaymentCondition` live in `SolveBeam\Twinfield\Dimension`, as suppliers (`CRD`) have them too.

Differences with the [documentation](https://developers.twinfield.com/documentation/api/master-data/customers/) that the SDK handles:

| Documentation | Twinfield |
|---|---|
| `<invoicing>`, `<groups>`, `<postingrules>` and `<paymentconditions>` | Left out of the response when empty |
| Posting rule lines have `dimension2` and `dimension3` | Left out when empty |
| A posting rule has a currency and amount | A rule can have an empty `<currency/>`, `<amount/>` and `<lines/>` |
| An address has a name | The name can be empty |
| `<remittanceadvice>` is not listed in the root elements | It is in every read response |

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
- [List customers](/examples/customers/list-customers/)
- [Read customer](/examples/customers/read-customer/)
- [Create customer](/examples/customers/create-customer/)
- [Update customer](/examples/customers/update-customer/)
- [Delete customer](/examples/customers/delete-customer/)
