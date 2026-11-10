---
title: REST API
description: The Twinfield REST API next to the SOAP and XML web services - scopes, company GUIDs, paging, errors, and which endpoints the SDK supports.
---

Next to the SOAP services and the XML web services, Twinfield has a REST API with JSON: [api.accounting.twinfield.com/Api/swagger/ui/index](https://api.accounting.twinfield.com/Api/swagger/ui/index). It covers parts that the other services don't have, such as the [new fixed assets module](/guides/fixed-assets/), sales articles and changes since a moment, and Twinfield is moving more functionality to it.

The SDK supports every endpoint of the public definition (`open-v1`). The resources look like the other resources of the SDK; you don't have to know whether a method uses SOAP, XML or REST, except for the [scopes](#scopes).

```php
$twinfield = Twinfield::create($accessTokenProvider)->company('1000');

foreach ($twinfield->fixedAssets()->search()->all() as $asset) {
    echo $asset->code, ' ', $asset->description, ' ', $asset->netBookValue, PHP_EOL;
}
```

## Swagger definitions in the repository

The Swagger UI loads two definitions, which the repository keeps in [`openapi/`](https://github.com/solvebeam/twinfield-php/tree/main/openapi) and updates daily, like the WSDL files:

| Definition | Content | In the SDK |
|---|---|---|
| `open-v1` "Twinfield Public REST API Resources" | 40 paths: articles, budgets, fixed assets, ledger accounts, VAT codes, … | All endpoints, see [Endpoints](#endpoints) |
| `private` "Twinfield Private REST API Resources" | 194 paths, e.g. bank statements and companies | Not supported: internal, can change without notice |

The `private` definition is kept to see when endpoints become public.

## Scopes

Every operation of the REST API needs a scope, on top of `openid twf.organisationUser twf.user`. Without it Twinfield answers HTTP 403 and the SDK throws an `AccessDeniedException`. The description of each operation in the Swagger UI lists its scopes; the `Scope` enum has them all:

| Scope | Endpoints |
|---|---|
| `readArticles`, `writeArticles` | Sales articles |
| `readBankAccounts` | PSD2 feeds |
| `readBookkeeping` | Ledger accounts |
| `readBookkeepingMatches` | Matching audit trail |
| `readBudgets`, `writeBudgets` | Budget lines, annual budgets, periods, import |
| `readBudgetScenarios`, `writeBudgetScenarios` | Budgets, spreading templates |
| `readCustomers`, `readSuppliers` | Changes of customers and suppliers, posting rules |
| `readFixedAssets` | Fixed assets (new module) |
| `readVat`, `readVatGroup` | VAT codes |

Request the scopes at login, and make sure your OAuth client may request them:

```php
use SolveBeam\Twinfield\OAuth2\Scope;

$url = $provider->getAuthorizationUrl([
    'scope' => Scope::values(...Scope::identity(), Scope::ReadFixedAssets, Scope::ReadVat),
]);
```

`AccessTokenValidation::$scopes` (and `hasScope()`) tell which scopes a token has. The ledger accounts accept any of `readBookkeeping`, `readBudgets`, `readCustomers`, `readFixedAssets`, `readSuppliers`, `readVat` and `readVatGroup`.

## Company GUIDs

The REST API identifies the organisation and the company by GUID, not by code: `/organisations/{organisationId}/companies/{companyId}/…`. The organisation GUID comes from the access token (`twf.organisationId`). Twinfield only returns the company GUID with the Finder (`OFF` with option `includeid`); a read of the office doesn't have it.

The SDK takes care of it. `$twinfield->companyId()` gives the GUID of the company of the client:

- from `company('1000', 'ffe2c8b3-…')`, when you pass it, e.g. from a shadow database;
- from an `OfficeSummary` of `offices()->list()`, which has `companyId`;
- or else the SDK lists the offices once (`offices()->list()`) and remembers the GUIDs of all companies for that `Twinfield` object.

The GUIDs of the REST API are the same as the `uid` of the dimensions in the XML web services: the GUID of a ledger account is the `uid` of the general ledger account, the `id` of a changed customer is the `uid` of the customer. Use `ledgerAccounts()->get($id)` to turn a GUID of an account, cost center or relation into its code.

## Lists and paging

Lists come as `{"items": […], "total": …, "filteredTotal": …}`: `total` is the number of items of the company, `filteredTotal` the number that match the filters. Searches of the SDK give a `RestPage` with the same numbers:

```php
$page = $twinfield->ledgerAccounts()->search('80*')->limit(100)->get();

echo count($page), ' of ', $page->filteredTotal, PHP_EOL;

foreach ($twinfield->ledgerAccounts()->search('80*')->all() as $account) { … }
```

Without a limit Twinfield gives 50 items. The maximum differs per endpoint: 250 for VAT codes, 200 for the matching audit trail, 1000 for posting rules, changes and fixed asset classes, 10000 for budgets and fixed assets; Twinfield refuses more with a `ValidationException`. `all()` pages with 250 items.

The matching audit trail has no offset: it pages by time (`since`), see [Matching](/guides/matching/).

## Fields

Some endpoints return only their default fields unless you ask for more with the `fields` parameter (`fields=*` for all, `fields=items/id,items/name` for some). The fixed assets endpoints only return `id`, `code`, `description` and `version` without it. The SDK asks for all fields where it matters.

## Numbers, dates and times

- The REST API sends amounts as JSON numbers (`121.1`, `0.000000`). The SDK reads them as exact strings, never as floats: `Amount` for amounts, `UnitPrice` for article prices (four decimals), `Percentage` for VAT rates, numeric strings for quantities and lifetimes. It sends them back as exact JSON numbers.
- Moments such as `createdAt` and `modified` are UTC. Twinfield doesn't always add the `Z`; the SDK reads them as UTC either way.
- Dates such as `investmentDate` are dates without time, at midnight in the time zone of PHP, like the other dates the SDK reads.

## Errors

| HTTP status | Exception |
|---|---|
| 401, 403 | `Soap\AccessDeniedException`, with a hint about the scope |
| 404 | `Rest\NotFoundException` |
| 400, 422 | `Rest\ValidationException`, e.g. "Article code is already in use" |
| 412 | `Rest\VersionConflictException`: the item changed since you read it |
| 429 | `Soap\RateLimitExceededException`, as for the SOAP services |
| other 4xx and 5xx | `Rest\RestException` |

All but the access and rate limit exceptions extend `RestException`, which has the `status` and the raw `body`. The message has the reason Twinfield gives: a JSON string (`"Requested resource was not found"`) or a message per parameter (`{"orderBy": "The value 'x' is not valid for OrderBy."}`).

A response the SDK doesn't expect, such as a missing field, throws an `UnexpectedResponseException` with the path, e.g. `items[2]/matchDate is missing.`

## Other endpoints

`$twinfield->rest()` calls any endpoint, with the access token and the GUIDs filled in; copy the path from the Swagger definition. It throws the same exceptions. See [REST API request](/examples/getting-started/rest-request/).

```php
$json = $twinfield->rest('GET', '/organisations/{organisationId}/companies/{companyId}/fixedassets/classes', ['fields' => '*'])->json();

foreach ($json->list('items') as $class) {
    echo $class->string('name'), PHP_EOL;
}
```

The body is an array (encoded with `JsonWriter`, which writes `Amount` and `JsonNumber` as exact numbers) or a `RawBody` for a file upload.

## Endpoints

"Verified live" means read live on test companies with the SDK. The write tests in `tests/Write` need `composer test:write`; until they have run, writing only follows the Swagger definition and is tested offline.

| Swagger tag | Endpoints | SDK | Status |
|---|---|---|---|
| Bookkeeping Ledger Accounts | `GET /bookkeeping/accounts`, `/{id}` | `ledgerAccounts()->search()`, `get()` | Verified live |
| Bookkeeping Audit Trails | `GET /bookkeeping/audittrails/matching` | `matching()->auditTrail()`, `auditTrailSince()` | Verified live |
| Posting Rules | `GET /bookkeeping/postingrule` | `postingRules()->modifiedSince()` | Verified live |
| Customers | `GET /sales/customers/changes` | `customers()->changes()` | Verified live; HTTP 500 in some companies |
| Suppliers | `GET /purchase/suppliers/changes` | `suppliers()->changes()` | Verified live |
| VAT Codes | `GET /vat/vatcodes`, `/{id}` | `vatCodes()->searchDetails()`, `getDetails()` | Verified live |
| Sales Articles | `GET /sales/articles`, `/{id}`, `/sales/articlecultures`, `/sales/articles/export` | `salesArticles()->search()`, `get()`, `find()`, `cultures()`, `export()` | Verified live |
| Sales Articles | `POST`, `PUT`, `DELETE /sales/articles`, `POST /sales/articles/import` | `create()`, `update()`, `save()`, `delete()`, `import()` | Tested offline |
| Budgets | `GET /budgeting/budgets`, `/{id}`, `/annual`, `/periods/{year}`, lines, export, import template | `budgets()->list()`, `getById()`, `annual()`, `periods()`, `lines()`, `accountLines()`, `line()`, `export()`, `importTemplate()` | Verified live |
| Budgets | `POST`, `PUT`, `DELETE` budgets and lines, copy, spread, import | `create()`, `update()`, `delete()`, `createLine()`, `updateLine()`, `deleteLine()`, `deleteLines()`, `deleteAccountLines()`, `copyLines()`, `spread()`, `import()` | Tested offline |
| Budgeting Spreading Templates | `GET /budgeting/spreadingtemplates/defaultdistribution` | `spreadingTemplates()->defaultDistribution()` | Verified live |
| Budgeting Spreading Templates | search, read, create, update, delete | `search()`, `get()`, `create()`, `update()`, `delete()` | Tested offline (no templates live) |
| Fixed Assets Assets | `GET /fixedassets/assets`, `/{id}`, `/{id}/actions` | `fixedAssets()->search()`, `get()`, `actions()` | Verified live |
| Fixed Assets Classes | `GET /fixedassets/classes`, `/{id}` | `fixedAssets()->classes()`, `getClass()` | Verified live |
| Fixed Assets Groups | `GET /fixedassets/groups`, `/{id}` | `fixedAssets()->groups()`, `getGroup()` | Groups verified live (empty); a group only offline |
| Fixed Assets Reports | `POST /fixedassets/backgroundreports/reconciliation`, status, download | `queueReconciliationReport()`, `isReconciliationReportReady()`, `downloadReconciliationReport()` | Tested offline; HTTP 403 live for the test user |
| Feeds | `GET /banks/feeds/psd2` | `bankFeeds()->psd2()` | Verified live (no feeds) |

Not wrapped: the `OPTIONS` requests and the link resources (`GET /budgeting`, `/bookkeeping/audittrails`), which only list the other endpoints, and `PATCH /sales/articles/{id}` (JSON Patch), which `update()` covers. Use `rest()` for those.
