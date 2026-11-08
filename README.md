<p align="center">
	<a href="https://php-twinfield.solvebeam.com/">
		<img src="images/twinfield/twinfield-icon.svg" alt="Twinfield" width="96" height="96">
	</a>
</p>

<h1 align="center">PHP Twinfield SDK</h1>

<p align="center">
	Modern PHP SDK for the Twinfield API, built on PSR standards.
	<br>
	<a href="https://php-twinfield.solvebeam.com/"><strong>Documentation</strong></a>
	·
	<a href="examples/">Examples</a>
</p>

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

foreach ($twinfield->periods()->list(2025) as $period) {
    echo $period->number, ' ', $period->name, PHP_EOL;
}

$activity = $twinfield->activities()->create(
    ActivityInput::create('A100', 'Consultancy')->customer('1010')->billable(true),
);
```

See [examples/](examples/) for small, runnable examples, starting with [Authentication](examples/getting-started/authentication/).

## Master data

Master data such as customers, general ledger accounts, activities, cost centers and fixed assets are dimensions, read and written with the XML web services. See the [master data guide](https://php-twinfield.solvebeam.com/guides/master-data/). Where live Twinfield responses differ from the API documentation, the SDK follows the live responses: see [Differences with the documentation](https://php-twinfield.solvebeam.com/guides/documentation-differences/).

| Dimension type | Resource | Status |
|---|---|---|
| Activities (`ACT`) | `$twinfield->activities()` | Search, read, create, update, delete, activate. See [List activities](examples/activities/list-activities/) and [Create activity](examples/activities/create-activity/). |
| Cost centers (`KPL`) | `$twinfield->costCenters()` | Search, read, create, update, delete, activate. See [List cost centers](examples/cost-centers/list-cost-centers/) and [Create cost center](examples/cost-centers/create-cost-center/). |
| Customers (`DEB`) | `$twinfield->customers()` | Search (also by bank account and address), read, create, update, delete, activate; addresses, bank accounts, SEPA direct debit, credit management, posting rules. See [Read customer](examples/customers/read-customer/) and [Create customer](examples/customers/create-customer/). |
| Fixed assets (`AST`) | `$twinfield->fixedAssets()` | Search (also by group), read, create, update, delete, activate; asset method, depreciation settings, free texts, transaction lines and locked settings. See [Read fixed asset](examples/fixed-assets/read-fixed-asset/) and [Create fixed asset](examples/fixed-assets/create-fixed-asset/). |
| General ledger accounts (`BAS`, `PNL`) | `$twinfield->balanceSheetAccounts()`, `$twinfield->profitAndLossAccounts()` | Search (also hidden), read, create, update, delete, activate; sub-analysis, child validations, VAT code, groups. See [Read general ledger account](examples/general-ledger-accounts/read-general-ledger-account/) and [Create general ledger account](examples/general-ledger-accounts/create-general-ledger-account/). |
| Projects (`PRJ`) | `$twinfield->projects()` | Search, read, create, update, delete, activate; valid dates, customer, rate, authoriser, billable, quantities and a VAT code for all activities. See [Read project](examples/projects/read-project/) and [Create project](examples/projects/create-project/). |
| Suppliers (`CRD`) | `$twinfield->suppliers()` | Search (also by bank account and address), read, create, update, delete, activate; credit transfer or cheques, external supplier number, remittance advice, addresses, bank accounts, posting rules. See [Read supplier](examples/suppliers/read-supplier/) and [Create supplier](examples/suppliers/create-supplier/). |

| Other master data | Resource | Status |
|---|---|---|
| Asset methods | `$twinfield->assetMethods()` | Search, read, create, update, delete, activate. See [Read asset method](examples/asset-methods/read-asset-method/) and [Create asset method](examples/asset-methods/create-asset-method/). |
| Bank books | `$twinfield->bankBooks()` | Search, read, create, update (Command & Query; Twinfield cannot delete them). See [Read bank book](examples/bank-books/read-bank-book/) and [Update bank book](examples/bank-books/update-bank-book/). |
| Cash books | `$twinfield->cashBooks()` | Search, read, create, update (Command & Query; Twinfield cannot delete them). See [Read cash book](examples/cash-books/read-cash-book/) and [Update cash book](examples/cash-books/update-cash-book/). |
| Currencies | `$twinfield->currencies()` | Search, read, create, update, delete, with exchange rates (`rateOn($date)`). See [Read currency](examples/currencies/read-currency/) and [Update currency rates](examples/currencies/update-currency-rates/). |
| Rates | `$twinfield->rates()` | Search (also by type: hours or quantities), read, create, update, delete, activate; prices per date range with `priceOn($date)` and `newPriceFrom()`. See [Read rate](examples/rates/read-rate/) and [Update rate price](examples/rates/update-rate-price/). |
| Dimension groups | `$twinfield->dimensionGroups()` | Search (also by dimension type), read, create, update, delete; dimensions of any type. See [Read dimension group](examples/dimension-groups/read-dimension-group/) and [Update dimension group](examples/dimension-groups/update-dimension-group/). |
| Dimension types | `$twinfield->dimensionTypes()` | Search (also by financial level and by dimension code), read, update name, code mask and address labels; `$type->mask->matches($code)`. See [Read dimension type](examples/dimension-types/read-dimension-type/) and [Update dimension type](examples/dimension-types/update-dimension-type/). |
| Budgets | `$twinfield->budgets()` | Search, budget vs. actual per account, cost center or project (Command & Query; read-only). See [Budget vs. actual](examples/budgets/budget-vs-actual/). |
| Offices | `$twinfield->offices()` | List and search (also without tax group) with company ID, read all settings: currencies, VAT, system dimensions, intercompany and locked settings (read-only). `company($office)` takes an office. See [Read office](examples/offices/read-office/) and [Search offices](examples/offices/search-offices/). |

## Transactions

Transactions are read and written with the XML web services. You add lines with `receive()` and `pay()`; the SDK chooses debit or credit and calculates the total line and the closing balance. Amounts are exact numeric strings (`Amount`), never floats. See the [transactions guide](https://php-twinfield.solvebeam.com/guides/transactions/).

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
| Bank | `$twinfield->bankTransactions()` | Read, create, update, save many, post, delete. See [Create bank transaction](examples/bank-transactions/create-bank-transaction/) and [Import bank statement](examples/bank-transactions/import-bank-statement/). |
| Cash | `$twinfield->cashTransactions()` | Read, create, update, save many, post, delete. See [Daily cash register](examples/cash-transactions/daily-cash-register/). |
| Sales, purchase, journal | – | Not yet; use `processXml()` |

## Command & Query web services

Twinfield has three kinds of web services: classic SOAP services such as the Finder, XML web services (ProcessXml) and [Command & Query web services](https://developers.twinfield.com/documentation/api/types/command-and-query-webservices). The last kind uses queries to read data and commands to change it. These services always work on one company, so call `company()` first.

| Service | Resource | Status |
|---|---|---|
| PeriodService | `$twinfield->periods()` | Queries `years()`, `list()`; all commands. See [List periods](examples/periods/list-periods/) and [Rename period](examples/periods/rename-period/). |
| BudgetService | `$twinfield->budgets()` | All queries: `totals()->byProfitAndLoss()`, `byCostCenter()`, `byProject()`. See [Budget vs. actual](examples/budgets/budget-vs-actual/). |
| BankBookService | `$twinfield->bankBooks()` | All queries and commands. See [Read bank book](examples/bank-books/read-bank-book/). |
| CashBookService | `$twinfield->cashBooks()` | All queries and commands. See [Read cash book](examples/cash-books/read-cash-book/). |
| DeletedTransactionsService | – | Not yet |
| BankStatementService | – | Not yet |
| TransactionBlockedValueService | – | Not yet |

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

Comments and whitespace between elements are removed before sending. A response with `result="0"` throws a `ProcessXmlException` with every error and warning and the path of its element. `XmlValue` formats and checks the value types (date, money, period, boolean, code). Listing companies works without company: `$twinfield->withoutCompany()->processXml('<list><type>offices</type></list>')`.

Not supported, because Twinfield deprecated them or they are only for .NET: `ProcessXmlCompressed`, `ProcessXmlDocument` and the `<general>` root element for bulk requests. See the [ProcessXml guide](https://php-twinfield.solvebeam.com/guides/process-xml/) and the examples [List companies](examples/process-xml/list-companies/) and [Read dimension](examples/process-xml/read-dimension/).

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

Authorization errors come as HTTP 401 or 403; without a SOAP fault in the response the SDK throws an `AccessDeniedException`. Too many requests give a `RateLimitExceededException`.

See the [error handling example](examples/getting-started/error-handling/) for all error codes.

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

Twinfield supports the following OAuth 2.0 scopes:

| Scope | Description | Claims |
|-------|-------------|--------|
| `openid` | Standard OpenID Connect scope. Required when using identity-related scopes. | - |
| `twf.user` | Contains Twinfield-specific information about the user (identity token).<br><br>**Note:** When adding this scope, add `openid` as well. | • `twf.id` - GUID of Twinfield ID<br>• `twf.organisationUserCode` - User code inside organisation<br>• `twf.organisationId` - GUID of organisation |
| `twf.organisation` | Contains organisation information. | • `twf.organisationCode` - Code of organisation<br>• `twf.organisationId` - GUID of organisation<br>• `twf.clusterUrl` - URL of cluster on which organisation is located |
| `twf.organisationUser` | Contains information about organisation user.<br><br>**⚠️ Important:** This scope is **mandatory** in order to login. | • `twf.organisationUserCode` - Code of the organisation user (used for login)<br>• `twf.organisationCode` - Code of the organisation<br>• `twf.organisationId` - GUID of organisation |
| `readFixedAssets` | Grants read access to Fixed Assets API endpoints. | - |
| `readVat` | Grants read access to VAT Codes API endpoints. | - |
| `offline_access` | Required to receive refresh tokens in the authorization code flow. | - |

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

Register `http://localhost:8765/callback` as redirect URI for your client in the Twinfield developer portal, then run:

```sh
composer twinfield:setup
```

This asks for your client ID, client secret and redirect URI, opens the Twinfield login in your browser and asks for a default company. Running any example for the first time does the same. Values are saved outside the project, in `~/.config/twinfield/` (only readable by you):

```dotenv
# ~/.config/twinfield/.env
TWINFIELD_CLIENT_ID=…
TWINFIELD_CLIENT_SECRET=…
TWINFIELD_REDIRECT_URI=http://localhost:8765/callback
TWINFIELD_COMPANY=1002
TWINFIELD_WRITE_COMPANIES=1002,1002_3   # optional, for write tests
```

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
| `composer twinfield:login` | Log in (again), e.g. when the refresh token has expired. Use `-- --manual` to paste the redirected URL. |
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

<table>
	<tr>
		<td align="center" width="50%">
			Made by<br><br>
			<a href="https://www.solvebeam.com/">
				<picture>
					<source media="(prefers-color-scheme: dark)" srcset="images/solvebeam/solvebeam-logo-dark.svg">
					<img src="images/solvebeam/solvebeam-logo.svg" alt="SolveBeam" height="40">
				</picture>
			</a>
		</td>
		<td align="center" width="50%">
			Twinfield is a product of<br><br>
			<a href="https://www.twinfield.com/">
				<picture>
					<source media="(prefers-color-scheme: dark)" srcset="images/wolters-kluwer/wolters-kluwer-logo-dark.svg">
					<img src="images/wolters-kluwer/wolters-kluwer-logo.svg" alt="Wolters Kluwer" height="32">
				</picture>
			</a>
		</td>
	</tr>
</table>

Twinfield and Wolters Kluwer are trademarks of Wolters Kluwer. Their names and logos are used only to refer to their products. See [images/](images/) for the logo files.

## Links

- https://chatgpt.com/c/6983bca8-83d0-8327-aafb-c4cb3a774cb6
- https://oauth2-client.thephpleague.com/providers/league/
- https://oauth2-client.thephpleague.com/providers/thirdparty/
- https://github.com/php-fig/cache
- https://github.com/phpro/soap-client
- https://www.w3.org/TR/soap/
- https://www.w3.org/TR/soap12/
