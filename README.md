<p align="center">
	<a href="https://php.twinfield.solvebeam.com/">
		<img src="images/twinfield/twinfield-icon.svg" alt="Twinfield" width="96" height="96">
	</a>
</p>

<h1 align="center">PHP Twinfield SDK</h1>

<p align="center">
	Modern PHP SDK for the Twinfield API, built on PSR standards.
	<br>
	<a href="https://php.twinfield.solvebeam.com/"><strong>Documentation</strong></a>
	·
	<a href="examples/">Examples</a>
</p>

## Contents

- [Installation](#installation)
- [Quick start](#quick-start)
- [Organisations and companies](#organisations-and-companies)
- [Master data](#master-data)
- [Transactions](#transactions)
- [Browse data](#browse-data)
- [Declarations](#declarations)
- [Hierarchies](#hierarchies)
- [Matching](#matching)
- [Pay & Collect](#pay--collect)
- [Command & Query web services](#command--query-web-services)
- [XML web services (ProcessXml)](#xml-web-services-processxml)
- [REST API](#rest-api)
- [Errors](#errors)
- [Backward compatibility](#backward-compatibility)
- [PSR Compliance](#psr-compliance)
- [OAuth2 Usage](#oauth2-usage)
- [OpenID Connect Authentication](#openid-connect-authentication)
  - [Scopes](#scopes)
  - [REST API scopes](#rest-api-scopes)
- [Development and testing](#development-and-testing)
- [About](#about)
- [Links](#links)

## Installation

The SDK needs PHP 8.3 or newer and a [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client with [PSR-17](https://www.php-fig.org/psr/psr-17/) factories.

```sh
composer require solvebeam/twinfield
```

The SDK does not ship an HTTP client. Install one you like, for example Symfony's client with Nyholm's PSR-7 implementation:

```sh
composer require symfony/http-client nyholm/psr7
```

`Twinfield::create()` finds an installed client and factories automatically through [php-http/discovery](https://docs.php-http.org/en/latest/discovery.html). See [Installation](https://php.twinfield.solvebeam.com/getting-started/installation/) for other clients and optional packages.

## Quick start

```php
use SolveBeam\Twinfield\Activity\ActivityInput;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Twinfield;

$twinfield = Twinfield::create($accessTokenProvider)->company('1000');

foreach ($twinfield->offices()->list() as $office) {
    echo $office->code, ' ', $office->name, PHP_EOL;
}

$debtors = $twinfield->finder(FinderType::Dimensions)
    ->option('dimtype', 'DEB')
    ->limit(10)
    ->get();

foreach ($twinfield->periods()->list(2025) as $details) {
    echo $details->period, ' ', $details->name ?? '-', PHP_EOL;
}

$activity = $twinfield->activities()->create(
    ActivityInput::create('A100', 'Consultancy')->customer('1010')->billable(true),
);
```

See [examples/](examples/) for small, runnable examples, starting with [Authentication](examples/getting-started/authentication/).

## Organisations and companies

Company and user codes are only unique within a Twinfield organisation: company `1000` can exist in several organisations. Every model the SDK reads therefore carries the organisation of the access token, either as `$office->organisation` and `$user->organisation`, or as `$customer->company` (a `CompanyReference` with the organisation and the company code). `$twinfield->company($customer->company)` refuses a company of another organisation. See [Organisations and companies](https://php.twinfield.solvebeam.com/guides/organisations/) for the keys to use in a shadow database.

```php
$customer = $twinfield->customers()->get('1000');

echo $customer->company->organisation->code, ' ', $customer->company->code, ' ', $customer->code, PHP_EOL;
```

## Master data

Master data such as customers, general ledger accounts, activities, cost centers and fixed assets are dimensions, read and written with the XML web services. See the [master data guide](https://php.twinfield.solvebeam.com/guides/master-data/). Where live Twinfield responses differ from the API documentation, the SDK follows the live responses: see [Differences with the documentation](https://php.twinfield.solvebeam.com/guides/documentation-differences/).

| Dimension type | Resource | Status |
|---|---|---|
| Activities (`ACT`) | `$twinfield->activities()` | Search, read, create, update, delete, activate. See [List activities](examples/activities/list-activities/) and [Create activity](examples/activities/create-activity/). |
| Cost centers (`KPL`) | `$twinfield->costCenters()` | Search, read, create, update, delete, activate. See [List cost centers](examples/cost-centers/list-cost-centers/) and [Create cost center](examples/cost-centers/create-cost-center/). |
| Customers (`DEB`) | `$twinfield->customers()` | Search (also by bank account and address), read, create, update, delete, activate; addresses, bank accounts, SEPA direct debit, credit management, posting rules. See [Read customer](examples/customers/read-customer/) and [Create customer](examples/customers/create-customer/). |
| Fixed assets, classic module (`AST`) | `$twinfield->classicFixedAssets()` | Search (also by group), read, create, update, delete, activate; asset method, depreciation settings, free texts, transaction lines and locked settings. See [Read fixed asset](examples/classic-fixed-assets/read-fixed-asset/) and [Create fixed asset](examples/classic-fixed-assets/create-fixed-asset/). |
| General ledger accounts (`BAS`, `PNL`) | `$twinfield->balanceSheetAccounts()`, `$twinfield->profitAndLossAccounts()` | Search (also hidden), read, create, update, delete, activate; sub-analysis, child validations, VAT code, groups. See [Read general ledger account](examples/general-ledger-accounts/read-general-ledger-account/) and [Create general ledger account](examples/general-ledger-accounts/create-general-ledger-account/). |
| Projects (`PRJ`) | `$twinfield->projects()` | Search, read, create, update, delete, activate; valid dates, customer, rate, authoriser, billable, quantities and a VAT code for all activities. See [Read project](examples/projects/read-project/) and [Create project](examples/projects/create-project/). |
| Suppliers (`CRD`) | `$twinfield->suppliers()` | Search (also by bank account and address), read, create, update, delete, activate; credit transfer or cheques, external supplier number, remittance advice, addresses, bank accounts, posting rules. See [Read supplier](examples/suppliers/read-supplier/) and [Create supplier](examples/suppliers/create-supplier/). |

| Other master data | Resource | Status |
|---|---|---|
| Articles | `$twinfield->articles()` | Search, read, create, update, delete, activate; the articles (items) of Classic sales invoices, with their sub-articles: prices, revenue account and cost center. See [Read article](examples/articles/read-article/) and [Create article](examples/articles/create-article/). |
| Asset methods, classic module | `$twinfield->classicAssetMethods()` | Search, read, create, update, delete, activate. See [Read asset method](examples/classic-asset-methods/read-asset-method/) and [Create asset method](examples/classic-asset-methods/create-asset-method/). |
| Bank books | `$twinfield->bankBooks()` | Search, read, create, update (Command & Query; Twinfield cannot delete them). See [Read bank book](examples/bank-books/read-bank-book/) and [Update bank book](examples/bank-books/update-bank-book/). |
| Cash books | `$twinfield->cashBooks()` | Search, read, create, update (Command & Query; Twinfield cannot delete them). See [Read cash book](examples/cash-books/read-cash-book/) and [Update cash book](examples/cash-books/update-cash-book/). |
| Currencies | `$twinfield->currencies()` | Search, read, create, update, delete, with exchange rates (`rateOn($date)`). See [Read currency](examples/currencies/read-currency/) and [Update currency rates](examples/currencies/update-currency-rates/). |
| Rates | `$twinfield->rates()` | Search (also by type: hours or quantities), read, create, update, delete, activate; prices per date range with `priceOn($date)` and `newPriceFrom()`. See [Read rate](examples/rates/read-rate/) and [Update rate price](examples/rates/update-rate-price/). |
| VAT codes | `$twinfield->vatCodes()` | Search (also by type: sales or purchases), read, create, update, delete, activate; percentages over time with `percentageOn($date)`, `vatOn($amount, $date)` and `newPercentageFrom()`, accounts and VAT groups. See [Read VAT code](examples/vat-codes/read-vat-code/) and [Update VAT percentage](examples/vat-codes/update-vat-percentage/). |
| Dimension groups | `$twinfield->dimensionGroups()` | Search (also by dimension type), read, create, update, delete; dimensions of any type. See [Read dimension group](examples/dimension-groups/read-dimension-group/) and [Update dimension group](examples/dimension-groups/update-dimension-group/). |
| Dimension types | `$twinfield->dimensionTypes()` | Search (also by financial level and by dimension code), read, update name, code mask and address labels; `$type->mask->matches($code)`. See [Read dimension type](examples/dimension-types/read-dimension-type/) and [Update dimension type](examples/dimension-types/update-dimension-type/). |
| Budgets | `$twinfield->budgets()` | Search, budget vs. actual per account, cost center or project (Command & Query; read-only). See [Budget vs. actual](examples/budgets/budget-vs-actual/). |
| Users | `$twinfield->users()` | List and search, read (also the user of the access token with `current()`), create, update, delete, activate; role, login status, company access with `grantAccessTo()` and `revokeAccessTo()`, invitations. See [Read user](examples/users/read-user/) and [Current user](examples/users/current-user/). |
| Offices | `$twinfield->offices()` | List and search (also without tax group) with company ID, read all settings: currencies, VAT, system dimensions, intercompany and locked settings (read-only). `company($office)` takes an office. See [Read office](examples/offices/read-office/) and [Search offices](examples/offices/search-offices/). |

## Transactions

Transactions are read and written with the XML web services. You add lines with `receive()` and `pay()`; the SDK chooses debit or credit and calculates the total line and the closing balance. Amounts are exact numeric strings (`Amount`), never floats. See the [transactions guide](https://php.twinfield.solvebeam.com/guides/transactions/).

```php
use SolveBeam\Twinfield\BankTransaction\BankTransactionInput;

$transaction = $twinfield->bankTransactions()->create(
    BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
        ->statement(245, startValue: '1000.00')
        ->receive('1300', '121.00', customer: '1000', invoice: '2026-0042')
        ->pay('4500', '10.00', vatCode: 'IH', vatValue: '2.10', description: 'Bank costs'),
);
```

| Transactions | Resource | Status |
|---|---|---|
| Bank | `$twinfield->bankTransactions()` | Read, create, update, save many, post, delete, match with other transactions. See [Create bank transaction](examples/bank-transactions/create-bank-transaction/) and [Import bank statement](examples/bank-transactions/import-bank-statement/). |
| Cash | `$twinfield->cashTransactions()` | Read, create, update, save many, post, delete. See [Daily cash register](examples/cash-transactions/daily-cash-register/). |
| Sales | `$twinfield->salesTransactions()` | Read, create, update, save many, post, delete, change pay status, open invoices (Finder `IVT`). See [Create sales transaction](examples/sales-transactions/create-sales-transaction/) and [Credit note](examples/sales-transactions/credit-note/). |
| Sales invoices | `$twinfield->salesInvoices()` | Classic invoicing module: read, create, update, calculate without saving, make final and read the booked sales transaction, invoice types (Finder `INV`). See [Create sales invoice](examples/sales-invoices/create-sales-invoice/) and [Read sales invoice](examples/sales-invoices/read-sales-invoice/). |
| Journal | `$twinfield->journalTransactions()` | Read, create, update, save many, post, delete; memorial, wages, opening balance and year end. See [Create journal transaction](examples/journal-transactions/create-journal-transaction/) and [Opening balance](examples/journal-transactions/opening-balance/). |
| Purchase | `$twinfield->purchaseTransactions()` | Read, create, update, save many, post, delete, change pay status, open invoices (Finder `IVT`). See [Create purchase transaction](examples/purchase-transactions/create-purchase-transaction/) and [Credit note](examples/purchase-transactions/credit-note/). |
| Electronic bank statements | `$twinfield->electronicBankStatements()` | Import bank statements and let Twinfield allocate the lines: create, create many. Read statements with their allocations and matchings (BankStatementService): list by statement date or creation date. See [Import electronic bank statement](examples/electronic-bank-statements/import-electronic-bank-statement/) and [List electronic bank statements](examples/electronic-bank-statements/list-electronic-bank-statements/). |
| Reversal | `$twinfield->reversalTransactions()` | Reversals of journal transactions: read, post final, post provisional, undo. See [Read reversal](examples/reversal-transactions/read-reversal/) and [Reverse journal transaction](examples/reversal-transactions/reverse-journal-transaction/). |
| Spread | `$twinfield->spreadTransactions()` | Spreads of sales and purchase transactions over a number of periods: read, post final, post provisional (both also per detail line), undo. See [Read spread](examples/spread-transactions/read-spread/) and [Spread purchase transaction](examples/spread-transactions/spread-purchase-transaction/). |
| Time entry | `$twinfield->timeEntries()` | Time and quantities of a user on a project and an activity: save, save many. See [Create time entry](examples/time-entries/create-time-entry/). |

## Browse data

Financial data such as general ledger transaction lines, open items and balances comes from [browse data](https://developers.twinfield.com/documentation/api/request-data/browse-data/): a browse code with the columns you want and selection criteria. The query is immutable and the rows are read strictly, with amounts as `Amount`. See the [browse data guide](https://php.twinfield.solvebeam.com/guides/browse-data/).

```php
use SolveBeam\Twinfield\BrowseData\BrowseCode;

$result = $twinfield->browse(BrowseCode::GeneralLedgerDetailsV3)
    ->columns('fin.trs.head.code', 'fin.trs.head.number', 'fin.trs.line.dim1', 'fin.trs.line.valuesigned')
    ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
    ->equal('fin.trs.head.status', 'normal')
    ->get();

foreach ($result as $row) {
    echo $row->string('fin.trs.head.code'), ' ', $row->int('fin.trs.head.number'), ' ', $row->amount('fin.trs.line.valuesigned'), PHP_EOL;
}
```

The definitions of all browse codes are in [`browse-definitions/`](browse-definitions/). See [Read browse definition](examples/browse-data/read-browse-definition/) and, for keeping a copy of the transactions, [Initial load](examples/shadow-database/initial-load/), [Incremental load](examples/shadow-database/incremental-load/) and [Validate balances](examples/shadow-database/validate-balances/).

## Declarations

VAT, ICT and tax group returns come from the [declarations service](https://developers.twinfield.com/documentation/api/miscellaneous/declarations/): list them per company or for the whole organisation, download them as XBRL or XML, get the payment reference and mark them as sent, approved or rejected. See the [declarations guide](https://php.twinfield.solvebeam.com/guides/declarations/).

```php
use SolveBeam\Twinfield\Declaration\DeclarationType;

$declarations = $twinfield->company('1000')->declarations();

foreach ($declarations->list(year: 2026) as $return) {
    if ($return->type === DeclarationType::VatTurnover) {
        file_put_contents($return->id . '.xbrl', $declarations->vatReturnXbrl($return->id));
    }
}
```

See [List declarations](examples/declarations/list-declarations/) and [Download VAT return](examples/declarations/download-vat-return/).

## Hierarchies

Hierarchies, reporting structures (rapportagestructuren) such as RGS, come from the [hierarchies service](https://developers.twinfield.com/documentation/api/miscellaneous/hierarchy/): read them with their nodes and the general ledger accounts of a company, draw them with `RecursiveTreeIterator`, save them, bind and unbind accounts, hide and delete them. See the [hierarchies guide](https://php.twinfield.solvebeam.com/guides/hierarchies/).

```php
$hierarchy = $twinfield->company('1000')->hierarchies()->get('TWFRGS32');

foreach (new RecursiveTreeIterator($hierarchy) as $line) {
    echo $line, PHP_EOL;
}
```

See [Print hierarchy tree](examples/hierarchies/print-hierarchy-tree/) and the other [hierarchy examples](examples/README.md#hierarchies).

## Matching

[Matching](https://developers.twinfield.com/documentation/api/miscellaneous/matching/) (afletteren) links existing transaction lines with each other, e.g. a sales invoice and its payment: fully, partly, or with a discount, write-off or exchange rate difference. [Undo match](https://developers.twinfield.com/documentation/api/miscellaneous/undo-match/) undoes a match set by its match number, which is in browse data. See the [matching guide](https://php.twinfield.solvebeam.com/guides/matching/).

```php
use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;
use SolveBeam\Twinfield\Matching\MatchInput;
use SolveBeam\Twinfield\Matching\WriteOff;

$matching = $twinfield->company('1000')->matching();

$matching->match(
    MatchInput::customers(new DateTimeImmutable('2026-11-10'))
        ->line('KAS', 202600015, 2)
        ->line('VRK', 202600071, 1, writeOff: WriteOff::discount('2.00')),
);

$matching->undo(DimensionTypeCode::AccountsReceivable, '1000', 12);
```

See [Match transactions](examples/matching/match-transactions/), [List match sets](examples/matching/list-match-sets/) and [Undo match](examples/matching/undo-match/).

## Pay & Collect

[Pay & Collect](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/) creates payment runs (paying suppliers) and direct debit runs (collecting from customers): select the open invoices, change the run, authorise it, post it to the in-transfer ledger and create the SEPA file(s). Every step checks the version of the run. See the [Pay & Collect guide](https://php.twinfield.solvebeam.com/guides/pay-and-collect/).

```php
use SolveBeam\Twinfield\PayAndCollect\PayRunInput;
use SolveBeam\Twinfield\PayAndCollect\PayRunModification;

$payAndCollect = $twinfield->company('1000')->payAndCollect();

$run = $payAndCollect->create(
    PayRunInput::create('SEPANLCT', new DateTimeImmutable('2026-11-12'), '2026/11')
        ->maxDueDate(new DateTimeImmutable('2026-11-30')),
)->run;

$version = $payAndCollect->modify(PayRunModification::of($run)->excludeSummary(2));
$version = $payAndCollect->authorise($run, $version);
$version = $payAndCollect->post($run, $version);
$files = $payAndCollect->createFiles($run, $version);
$payAndCollect->finish($run, $files->version);
```

See [List pay runs](examples/pay-and-collect/list-pay-runs/), [Create pay run](examples/pay-and-collect/create-pay-run/) and [Pay run lifecycle](examples/pay-and-collect/pay-run-lifecycle/).

## Command & Query web services

Twinfield has three kinds of web services: classic SOAP services such as the Finder, XML web services (ProcessXml) and [Command & Query web services](https://developers.twinfield.com/documentation/api/types/command-and-query-webservices). The last kind uses queries to read data and commands to change it. These services always work on one company, so call `company()` first.

| Service | Resource | Status |
|---|---|---|
| PeriodService | `$twinfield->periods()` | Queries `years()`, `list()`, `get()`/`find()` for one `Period` (e.g. of a transaction); all commands. See [Periods](https://php.twinfield.solvebeam.com/guides/periods/), [List periods](examples/periods/list-periods/), [Period of transaction](examples/periods/period-of-transaction/) and [Rename period](examples/periods/rename-period/). |
| BudgetService | `$twinfield->budgets()` | All queries: `totals()->byProfitAndLoss()`, `byCostCenter()`, `byProject()`. See [Budget vs. actual](examples/budgets/budget-vs-actual/). |
| BankBookService | `$twinfield->bankBooks()` | All queries and commands. See [Read bank book](examples/bank-books/read-bank-book/). |
| CashBookService | `$twinfield->cashBooks()` | All queries and commands. See [Read cash book](examples/cash-books/read-cash-book/). |
| DeletedTransactionsService | `$twinfield->deletedTransactions()` | The only query: `list()`, filtered by day book and deletion date. See [List deleted transactions](examples/deleted-transactions/list-deleted-transactions/). |
| BankStatementService | `$twinfield->electronicBankStatements()` | Both queries: `list()` by statement date and `listByCreationDate()`. See [List electronic bank statements](examples/electronic-bank-statements/list-electronic-bank-statements/). |
| TransactionBlockedValueService | `$twinfield->transactionBlockedValues()` | Both commands: `register()` and `unregister()` a blocked value on a transaction line. See [Register blocked value](examples/transaction-blocked-values/register-blocked-value/). |

Services without a resource can be called directly. Give the fields in the order of the service's WSDL (usually alphabetical):

```php
use SolveBeam\Twinfield\CommandQuery\Command;
use SolveBeam\Twinfield\CommandQuery\Query;

$result = $twinfield->query('PeriodService', new Query('GetPeriods', ['Year' => 2025]), 'GetPeriodsResult');

foreach ($result->list('Periods', 'Period') as $period) {
    echo $period->int('Number'), ' ', $period->string('Name'), PHP_EOL;
}

$twinfield->process('PeriodService', new Command('ClosePeriod', ['PeriodNumber' => 1, 'Year' => 2025]));
```

The readers of the result (`string()`, `int()`, `bool()`, `dateTime()`, `list()`, `ints()`) throw an `UnexpectedResponseException` when a value is missing or invalid.

## XML web services (ProcessXml)

`processXml()` sends a request to the [XML web services](https://developers.twinfield.com/documentation/api/types/xml-web-services/) (`ProcessXmlString` on `processxml.asmx`) and returns the response as DOM document:

```php
$result = $twinfield->processXml('<read><type>dimensions</type><office>1000</office><dimtype>DEB</dimtype><code>1000</code></read>');

echo $result->xpath()->evaluate('string(/dimension/name)'), PHP_EOL;
```

Comments and whitespace between elements are removed before sending. A response with `result="0"` throws a `ProcessXmlException` with every error and warning and the path of its element. Listing companies works without company: `$twinfield->withoutCompany()->processXml('<list><type>offices</type></list>')`.

Not supported, because Twinfield deprecated them or they are only for .NET: `ProcessXmlCompressed`, `ProcessXmlDocument` and the `<general>` root element for bulk requests. See the [ProcessXml guide](https://php.twinfield.solvebeam.com/guides/process-xml/) and the examples [List companies](examples/process-xml/list-companies/) and [Read dimension](examples/process-xml/read-dimension/).

## REST API

Twinfield also has a [REST API](https://api.accounting.twinfield.com/Api/swagger/ui/index) with JSON. The SDK supports all endpoints of its public definition; the definitions are in [`openapi/`](openapi/), updated daily like the WSDL files. Every REST operation needs its own scope (`readFixedAssets`, `readVat`, `writeArticles`, …, see the `Scope` enum), and the REST API identifies the company by GUID: `$twinfield->companyId()` looks it up once. See the [REST API guide](https://php.twinfield.solvebeam.com/guides/rest-api/).

| Swagger tag | Resource | Status |
|---|---|---|
| Fixed Assets | `$twinfield->fixedAssets()` | The new fixed assets module: search, read with depreciation per regime, history, classes, groups, reconciliation report. Read-only. The classic module is `classicFixedAssets()`. See [Fixed assets](https://php.twinfield.solvebeam.com/guides/fixed-assets/), [List fixed assets](examples/fixed-assets/list-fixed-assets/) and [Read fixed asset](examples/fixed-assets/read-fixed-asset/). |
| Sales Articles | `$twinfield->salesArticles()` | Search, read (`get()`, `find()`), create, update, `save()`, delete, cultures, import and export (CSV, XLSX). A separate list: the articles of Classic sales invoices are `articles()`. See [List sales articles](examples/sales-articles/list-sales-articles/) and [Create sales article](examples/sales-articles/create-sales-article/). |
| Budgets | `$twinfield->budgets()` | Next to search and totals: budgets with GUIDs, create, update, delete, annual balances, periods, lines, copy, spread, import and export. See [List budget lines](examples/budgets/list-budget-lines/) and [Create budget](examples/budgets/create-budget/). |
| Budgeting Spreading Templates | `$twinfield->spreadingTemplates()` | Search, read, create, update, delete and the default distribution. |
| Bookkeeping Ledger Accounts | `$twinfield->ledgerAccounts()` | Search and read all accounts and dimensions with their GUID (the `uid` of the dimension). See [List ledger accounts](examples/ledger-accounts/list-ledger-accounts/). |
| Bookkeeping Audit Trails | `$twinfield->matching()->auditTrail()` | The matches and undone matches since a moment. See [Matching audit trail](examples/matching/matching-audit-trail/). |
| Customers, Suppliers | `customers()->changes()`, `suppliers()->changes()` | Relations changed since a moment, also deleted and archived ones. See [List changed customers](examples/customers/list-changed-customers/). |
| Posting Rules | `$twinfield->postingRules()` | Posting rules of customers and suppliers changed since a moment. See [List posting rules](examples/posting-rules/list-posting-rules/). |
| VAT Codes | `vatCodes()->searchDetails()`, `getDetails()` | VAT codes with GUIDs, rates over time and allocation rules. See [List VAT code rates](examples/vat-codes/list-vat-code-rates/). |
| Feeds | `$twinfield->bankFeeds()->psd2()` | PSD2 feeds and when their consent expires. See [List PSD2 feeds](examples/bank-feeds/list-psd2-feeds/). |

Other endpoints can be called directly; `{organisationId}` and `{companyId}` are filled in:

```php
$json = $twinfield->rest('GET', '/organisations/{organisationId}/companies/{companyId}/vat/vatcodes', ['limit' => 10])->json();

foreach ($json->list('items') as $vatCode) {
    echo $vatCode->string('code'), PHP_EOL;
}
```

## Errors

Twinfield reports errors as SOAP faults; the SDK throws them as `SoapFaultException`. Besides the message, the exception has the fault detail, so you can act on an error without depending on the (language dependent) message:

```php
use SolveBeam\Twinfield\Period\PeriodServiceFaultCode;
use SolveBeam\Twinfield\Soap\SoapFaultException;

try {
    $twinfield->periods()->list(1901);
} catch (SoapFaultException $exception) {
    $exception->detailType;    // 'PeriodServiceFault' (Command & Query services)
    $exception->detailCode;    // 'YearNotFound', or e.g. '354' for classic services
    $exception->detailMessage; // 'Year 1901 bestaat niet.'
    $exception->source;        // called method (classic services only)
}
```

Match `detailCode` with `ErrorCode` for the general codes of the classic services (see [Guidelines](https://developers.twinfield.com/documentation/api/types/guidelines)), e.g. `ErrorCode::TokenInvalid`, or with the codes of a Command & Query service such as `PeriodServiceFaultCode`.

Authorization errors come as HTTP 401 or 403; without a SOAP fault in the response the SDK throws an `AccessDeniedException`. Too many requests give a `RateLimitExceededException`; `$twinfield->lastRateLimit()` returns the rate limit headers of the most recent response, to slow down before that. Wrap the HTTP client in a `RateLimitRetryClient` to wait for `Retry-After` and retry automatically. See the [rate limits guide](https://php.twinfield.solvebeam.com/guides/rate-limits/).

See the [error handling example](examples/getting-started/error-handling/) for all error codes.

## Backward compatibility

The SDK follows [Semantic Versioning](https://semver.org/). The public API is what you use to work with Twinfield:

- the `Twinfield` client and the resources it returns (`$twinfield->customers()`, …);
- models, inputs, searches, enums and value objects (`Amount`, `Period`, …);
- exceptions;
- the low-level methods `processXml()`, `query()`, `process()` and `rest()` with their results (`ProcessXmlResult`, `ResultElement`, `RestResponse`, `JsonValue`).

Classes and methods marked `@internal` are not part of it and can change in any release. That is how the SDK talks to Twinfield: the SOAP and REST clients, the XML and JSON readers and writers, the request classes, and the methods that map a model from or to XML or JSON (`fromXml()`, `toXml()`, …). Twinfield is moving from SOAP/XML to REST/JSON, and that layer moves with it. The examples print a request with `toXml()` to show what would be sent; read that output, don't build on it. PHPStan and PhpStorm report the use of internal code.

Nearly all classes are `final`: wrap them instead of extending them. The exceptions are base classes you catch, such as `SoapFaultException` and `RestException`.

## PSR Compliance

### PSR-1 (Basic Coding Standard)

The codebase follows PSR-1 fundamentals for PHP files and class structure.

### PSR-2 (Coding Style Guide)

PSR-2 is superseded by PSR-12; this project follows PSR-12.

### PSR-4 (Autoloading)

The SDK uses PSR-4 autoloading for the `SolveBeam\Twinfield\` namespace, configured in `composer.json`.

### PSR-6 (Caching)

Examples use PSR-6 caching for token storage via `symfony/cache`.

- https://www.php-fig.org/psr/psr-6/
- https://github.com/php-fig/cache
- https://packagist.org/packages/psr/cache
- https://github.com/symfony/cache

### PSR-7 (HTTP Messages)

The OAuth2 provider relies on PSR-7 request/response interfaces for HTTP messages.

### PSR-18 (HTTP Client)

The library can work with PSR-18 HTTP clients. Suggested implementations are listed in `composer.json` (e.g., `symfony/http-client`, `php-http/curl-client`) along with a PSR-7 implementation such as `nyholm/psr7`.

### PSR-12 (Coding Style)

Coding style follows PSR-12, enforced via the PHPCS ruleset in `phpcs.xml.dist`.

## OAuth2 Usage

The provider comes from [solvebeam/oauth2-twinfield](https://github.com/solvebeam/twinfield-oauth2-php), which is installed with the SDK.

```php
<?php

use SolveBeam\Twinfield\OAuth2\Provider\Twinfield;

$provider = new Twinfield([
	'clientId' => 'your-client-id',
	'clientSecret' => 'your-client-secret',
	'redirectUri' => 'https://example.com/callback',
]);

$authorizationUrl = $provider->getAuthorizationUrl();

// Redirect the user to $authorizationUrl, then handle the callback.

$accessToken = $provider->getAccessToken('authorization_code', [
	'code' => $_GET['code'],
]);

$resourceOwner = $provider->getResourceOwner($accessToken);
$profile = $resourceOwner->toArray();
```

## OpenID Connect Authentication

### Scopes

The OpenID Connect scopes identify the user and keep the session; every API needs them:

| Scope | Description | Claims |
|-------|-------------|--------|
| `openid` | Standard OpenID Connect scope. Required when using identity-related scopes. | - |
| `twf.user` | Contains Twinfield-specific information about the user (identity token).<br><br>**Note:** When adding this scope, add `openid` as well. | • `twf.id` - GUID of Twinfield ID<br>• `twf.organisationUserCode` - User code inside organisation<br>• `twf.organisationId` - GUID of organisation |
| `twf.organisation` | Contains organisation information. | • `twf.organisationCode` - Code of organisation<br>• `twf.organisationId` - GUID of organisation<br>• `twf.clusterUrl` - URL of cluster on which organisation is located |
| `twf.organisationUser` | Contains information about organisation user.<br><br>**⚠️ Important:** This scope is **mandatory** in order to login. | • `twf.organisationUserCode` - Code of the organisation user (used for login)<br>• `twf.organisationCode` - Code of the organisation<br>• `twf.organisationId` - GUID of organisation |
| `offline_access` | Required to receive refresh tokens in the authorization code flow. | - |

#### REST API scopes

Every operation of the REST API needs one of the `read…`/`write…` scopes on top of the identity scopes; without it Twinfield answers HTTP 403. The `Scope` enum has them all (`Scope::rest()` lists the REST API scopes); see the [REST API guide](https://php.twinfield.solvebeam.com/guides/rest-api/#scopes) for which endpoint needs which scope.

| Scope | Description | SDK |
|-------|-------------|-----|
| `readArticles` | Read sales articles. | `salesArticles()`: search, read, cultures, export |
| `writeArticles` | Create, update, delete and import sales articles (also reads them). | `salesArticles()`: create, update, delete, import |
| `readBankAccounts` | PSD2 bank feeds. | `bankFeeds()->psd2()` |
| `readBookkeeping` | Ledger accounts. | `ledgerAccounts()` |
| `readBookkeepingMatches` | Matching audit trail. | `matching()->auditTrail()`, `auditTrailSince()` |
| `readBudgets` | Budget lines, annual budgets and budget periods. | `budgets()`: lines, annual, periods, export |
| `writeBudgets` | Change, copy, spread and import budget lines (also reads them). | `budgets()`: line methods, copy, spread, import |
| `readBudgetScenarios` | Budgets and spreading templates. | `budgets()->list()`, `getById()`, `spreadingTemplates()` |
| `writeBudgetScenarios` | Create, update and delete budgets and spreading templates (also reads them). | `budgets()->create()`, `update()`, `delete()`, `spreadingTemplates()` |
| `readCustomers` | Changed customers, posting rules and ledger accounts. | `customers()->changes()`, `postingRules()` |
| `readSuppliers` | Changed suppliers, posting rules and ledger accounts. | `suppliers()->changes()`, `postingRules()` |
| `readFixedAssets` | Fixed assets of the new fixed assets module, with classes, groups and reports. | `fixedAssets()` |
| `readVat` | VAT codes. | `vatCodes()->searchDetails()`, `getDetails()` |
| `readVatGroup` | VAT groups; also gives access to the ledger accounts. | `ledgerAccounts()` |

### Example with Scopes

```php
<?php

use SolveBeam\Twinfield\OAuth2\Provider\Twinfield;

$provider = new Twinfield([
	'clientId' => 'your-client-id',
	'clientSecret' => 'your-client-secret',
	'redirectUri' => 'https://example.com/callback',
]);

// Request authorization with specific scopes
$authorizationUrl = $provider->getAuthorizationUrl([
	'scope' => ['openid', 'twf.user', 'twf.organisation', 'twf.organisationUser', 'offline_access']
]);

// Redirect the user to $authorizationUrl, then handle the callback.

$accessToken = $provider->getAccessToken('authorization_code', [
	'code' => $_GET['code'],
]);
```

For more details, see the [Twinfield OpenID Connect documentation](https://developers.twinfield.com/documentation/api/authentication/openid-connect-auth).

## Development and testing

### One-time setup

Run:

```sh
composer twinfield:setup
```

This asks whether you have your own Twinfield client ID:

- **Yes**: it asks for your client ID, client secret and redirect URI. Register `http://localhost:8765/callback` as redirect URI for your client in the Twinfield developer portal first.
- **No**: it registers this checkout with [Twinfield Connect](https://php.twinfield.solvebeam.com/guides/twinfield-connect/) and logs in through the Twinfield client of SolveBeam.

Then it opens the Twinfield login in your browser and asks for a default company. Running any example for the first time does the same. Values are saved outside the project, in `~/.config/twinfield/` (only readable by you):

```dotenv
# ~/.config/twinfield/.env
TWINFIELD_CLIENT_ID=…
TWINFIELD_CLIENT_SECRET=…
TWINFIELD_REDIRECT_URI=http://localhost:8765/callback
TWINFIELD_COMPANY=1002
TWINFIELD_WRITE_COMPANIES=1002,1002_3   # optional, for write tests
```

With Twinfield Connect the file has `TWINFIELD_CONNECT_CLIENT_ID` and `TWINFIELD_CONNECT_CLIENT_SECRET` instead of the client ID and secret.

The refresh token is stored in `~/.config/twinfield/token.json`; access tokens are refreshed automatically.

An optional `.env` in the project root (gitignored) overrides values, and real environment variables override both. Set `TWINFIELD_CONFIG_DIR` to use another folder.

### Commands

| Command | What it does |
|---|---|
| `composer test` | Offline unit tests on fixtures. Fast, no network. |
| `composer test:live` | Read-only tests against the live API (`tests/Integration`), including running every example. Skipped without login. |
| `composer test:write` | Write tests (`tests/Write`). Asks you to type the company code first; only companies in `TWINFIELD_WRITE_COMPANIES`. |
| `composer twinfield -- finder DIM "*" 10` | Try a live call and see the SOAP request and response. Also `periods [year]`, `whoami`, `refresh`, `--company=CODE`, `--quiet`. |
| `composer twinfield:setup` | Guided setup: credentials, login and default company. Run again to change them. |
| `composer twinfield:login` | Log in (again), e.g. when the refresh token has expired. Use `-- --manual` to paste the redirected URL, and `-- --rest` to also request the scopes of the REST API (tests of the REST API are skipped without them). |
| `composer twinfield:anonymize` | Turn recorded responses into anonymized fixtures. |

### Environment switches

- `TWINFIELD_DEBUG=1`: print every SOAP request and response (access token masked).
- `TWINFIELD_RECORD=1`: save raw responses to `tests/Fixtures/raw/` (gitignored, contains real data).
- `TWINFIELD_COMPANY=1002_3`: run against another administration.

### Fixtures from real responses

```sh
TWINFIELD_RECORD=1 composer test:live
composer twinfield:anonymize
git diff tests/Fixtures/soap
```

The anonymizer keeps the XML structure and replaces every value with a fake value in the same format (digits stay digits, dates stay valid dates). It uses a secret key in `~/.config/twinfield/anonymizer.key`, so the same input always gives the same output. Check the list of kept values it prints before committing.

### Safety

All live calls go through a write guard. Requests that may change data (anything that is not a search, get, query or ProcessXml `read`/`list`/browse) are blocked unless writes were confirmed for that company via `composer test:write`.

## About

This library is made by [SolveBeam](https://www.solvebeam.com/). It is not an official Wolters Kluwer product. Twinfield is a product of [Wolters Kluwer](https://www.twinfield.com/).

Twinfield and Wolters Kluwer are trademarks of Wolters Kluwer. Their names and logos are used only to refer to their products. See [images/](images/) for the sources of the logo files.

## License

This library is released under the [MIT License](LICENSE).

The license covers the code and documentation written by SolveBeam. Material from Twinfield in this repository, such as the WSDL files, the OpenAPI specification, the web services manuals, the browse definitions and the quoted knowledge base articles, remains the property of Wolters Kluwer and is included for reference only.

## Links

- [Changelog](CHANGELOG.md)
- https://oauth2-client.thephpleague.com/providers/league/
- https://oauth2-client.thephpleague.com/providers/thirdparty/
- https://github.com/php-fig/cache
- https://github.com/phpro/soap-client
- https://www.w3.org/TR/soap/
- https://www.w3.org/TR/soap12/
