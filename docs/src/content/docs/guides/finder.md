---
title: Finder
description: Search master data with the Twinfield Finder, with options, paging, other companies and errors.
---

The [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) (`finder.asmx`, method `Search`) searches master data by code and name: dimensions, VAT codes, currencies, periods, users and more. The resources use it for their `search()`, e.g. `$twinfield->customers()->search('10*')`. For the other Finder types, use `finder()` directly.

## Searching

`finder()` starts an immutable `FinderQuery` for a `FinderType`. Every method returns a new query.

```php
$result = $twinfield->finder(FinderType::Dimensions)
    ->pattern('13*')            // * matches anything, ? one character
    ->field(2)                  // 0 code and name (default), 1 code, 2 name
    ->option('dimtype', 'DEB')  // any Finder option
    ->limit(10)                 // rows per page (default 100)
    ->offset(20)                // rows to skip
    ->get();

echo $result->totalRows, PHP_EOL;

foreach ($result as [$code, $name]) {
    echo $code, ' ', $name, PHP_EOL;
}
```

`get()` returns one page as a `FinderResult` with `totalRows` (over all pages), `columns` and `rows`. `all()` goes through all pages, fetching the next page only when you get there. Column names are in the language of the user (`Naam`, `Name`), so read values by position.

The resource searches (`CodeNameSearch`) map the rows to summaries and have methods for the options, e.g. `inName()`, `inBankAccount()`, `modifiedSince()`, `includeHidden()` and `vatType()`. `finderQuery()` gives the underlying query.

## Finder types

`FinderType` has all types of the documentation that work live. Unless noted, the columns are code and name.

| Type | Case | Columns and notes |
|---|---|---|
| `ART` | `Articles` | Items |
| `ASM` | `AssetMethods` | |
| `BDS` | `Budgets` | |
| `BNK` | `CashbooksAndBanks` | Option `banktype`: `0` bank books, `1` cash books |
| `BSN` | `BillingSchedules` | |
| `CDA` | `CreditManagementActionCodes` | |
| `CER` | `Certificates` | |
| `CTR` | `Countries` | |
| `CUR` | `Currencies` | |
| `DIM` | `Dimensions` | Option `dimtype`; extra columns with search field 3, `includehidden` and `modifiedsince` |
| `DMT` | `DimensionTypes` | |
| `DVT` | `VatTypes` | |
| `FLT` | `FilterMappings` | |
| `FMT` | `PaymentFiles` | |
| `GRP` | `DimensionGroups` | |
| `GWY` | `Gateways` | |
| `HIE` | `Hierarchies` | Reporting structures |
| `HND` | `HierarchyNodes` | Option `hierarchy` |
| `ICTCOUNTRY` | `IctCountries` | |
| `INV` | `InvoiceTypes` | |
| `IVT` | `OpenInvoices` | Invoice number, amount, customer, name, debit/credit |
| `MAT` | `MatchingTypes` | |
| `OFF` | `Offices` | Code, name, country, VAT period, first quarter; `includeid` adds the ID |
| `OFG` | `OfficeGroups` | |
| `OIC` | `InterCompanyOffices` | |
| `PAY` | `PaymentTypes` | |
| `PIS` | `PayingInSlips` | Number, amount, debit/credit, description |
| `PRD` | `Periods` | Title (`2025/01`) and name |
| `REP` | `Reports` | |
| `REW` | `WordTemplates` | |
| `RMD` | `ReminderScenarios` | |
| `ROL` | `UserRoles` | |
| `SAR` | `SubItems` | Option `article` is mandatory |
| `SPM` | `DistributionsByPeriods` | |
| `TEQ` | `TimeAndQuantitiesTransactionTypes` | |
| `TRS` | `TransactionTypes` | Day books |
| `TRT` | `Rates` | |
| `TXG` | `TaxGroups` | |
| `USR` | `Users` | |
| `VAT` | `VatCodes` | |
| `VATN` | `VatNumbers` | Code, name, country, VAT number, company name, city |
| `VGM` | `VatGroupCountries` | Country, code, name |
| `VTB` | `VatGroups` | |
| `XLT` | `Translations` | |

`CQT` (cheques) is left out: it fails with a server error. See [Differences with the documentation](/guides/documentation-differences/#finder).

## Companies

The Finder searches in the company of the client (`$twinfield->company('1000')`), sent in the SOAP header. To search in another company, use `company()`:

```php
$twinfield->finder(FinderType::VatCodes)->company('1001')->get();
$twinfield->vatCodes()->search()->company('1001')->all();
```

This sends the Finder option `company`. Twinfield still accepts the deprecated option `office`, but refuses both together; the SDK throws an `InvalidArgumentException` before sending.

### All companies

`inAllCompanies()` (or `allCompanies()` on a `FinderQuery`) sends `company=*`: one search over all companies the user has access to. Twinfield adds two columns to every row, the company code and the company ID, and the SDK gives every summary its own `company`:

```php
foreach ($twinfield->vatCodes()->search()->inAllCompanies()->all() as $summary) {
    echo $summary->company->code, ' ', $summary->code, PHP_EOL;
}
```

This works for dimensions, VAT codes, bank books, cash books, currencies and dimension types (and with `finder()` also for `PAY`, `TRS`, `VATN` and `VGM`). Other types ignore `company=*` (the rows have no company), so `inAllCompanies()` refuses them. Together with `inBankAccount()` Twinfield fails with a server error, so that is refused too. See the [example](/examples/finder/all-companies/).

### Changes in a whole organisation

With `modifiedSince()`, one search gives everything that changed in all companies of the organisation, e.g. for a shadow database of the master data:

```php
$since = new DateTimeImmutable('2026-10-01 00:00:00', new DateTimeZone('UTC'));

foreach ($twinfield->customers()->search()->inAllCompanies()->modifiedSince($since)->all() as $summary) {
    // Read the full customer only for what changed.
    $customer = $twinfield->company($summary->company)->customers()->get($summary->code);
}
```

Live, this gave the 557 customers changed since 1 October 2026 in all companies of an organisation in one request. The same works for suppliers, general ledger accounts, cost centers, projects, activities and fixed assets (all dimensions), VAT codes, bank books, cash books and currencies.

`company=*` was not always there. Before, a shadow database of an organisation with 200 companies needed a search per company and per kind of master data to find what changed since the last sync: 200 requests to find the changed customers, 200 more for suppliers, and so on, mostly with nothing changed. Twinfield added `*` on request of the author of this SDK. Since the [rate limits](/guides/rate-limits/) of 1 December 2024 this matters even more: a Finder search costs 1 API credit, so 200 searches are a fifth of the credits per minute of an organisation, and one search is one credit.

## Errors

When Twinfield refuses a search, e.g. an option the type doesn't support, `get()` and `all()` throw a `FinderException` with the messages of Twinfield:

```php
try {
    $twinfield->finder(FinderType::SubItems)->get();
} catch (FinderException $exception) {
    $message = $exception->messages[0];

    if ($message->code === FinderErrorCode::OptionMandatory) {
        echo 'Missing option ', $message->parameters[0], PHP_EOL; // article
    }
}
```

`$message->text` is in the language of the user; use `$message->code` to act on it. Codes seen live: `OptionNotAllowed`, `OptionMandatory`, `ParameterOutOfRange` (search field outside 0–4) and `OptionCompanyAndOptionOfficeCannotBeBothProvided`.
