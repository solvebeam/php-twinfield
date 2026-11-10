# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-11-10

First release.

### Added

- Backward compatibility: the transport layer is marked `@internal` and is not part of the public API: `SoapClient`, `RestClient`, the SOAP envelope and request classes, `XmlBuilder`, `XmlElement`, `XmlValue`, `JsonWriter`, `Twinfield::call()` and the mapping methods on models (`fromXml()`, `toXml()`, `fromResult()`, `fromFinderRow()`, …). Use the resources, or `Twinfield::processXml()`, `query()`, `process()` and `rest()`.

#### Client and authentication

- `Twinfield` client for the Twinfield web services, built on PSR-7, PSR-17 and PSR-18. Requires PHP 8.3 or higher with the `bcmath`, `dom`, `libxml`, `mbstring` and `simplexml` extensions.
- OAuth2 and OpenID Connect login, with access token providers that refresh the access token.
- Organisations and companies: every model knows its organisation and company, select a company with `Twinfield::company()`.
- Scopes for OpenID Connect and the REST API.
- Errors from the Twinfield guidelines: `AccessDeniedException`, `ClassicServiceFaultException` and `CommandQueryFaultException` for SOAP faults, `NotFoundException` and `AlreadyExistsException` per resource, and `UnexpectedResponseException` for responses that don't match what we expect.
- Rate limits: `RateLimitExceededException` with the rate limit headers, `Twinfield::lastRateLimit()` and the opt-in `RateLimitRetryClient`.

#### Value objects

- `Amount` (2 decimals) and `UnitPrice` (4 decimals) for money as numeric strings with bcmath.
- `Period` and `PeriodDetails` to link transactions to years and periods.
- `EmailAddress` with the Twinfield e-mail address validation, used for users and addresses of customers and suppliers.
- `Guid` for company and other GUIDs of the REST API.

#### Companies, periods and users

- Offices (companies): list, search and read all settings (`OfficeResource`).
- Years and periods of a company (`PeriodResource`).
- Users: search, read, create, update, delete and activate, and the current user (`UserResource`).
- Finder: all Finder types, the `company` option, organisation-wide searches and `FinderException`.

#### Master data

Search, read, create, update and delete (where Twinfield supports it):

- Activities (`ActivityResource`), projects (`ProjectResource`) and rates (`RateResource`).
- Customers (`CustomerResource`) and suppliers (`SupplierResource`), including `changes()` since a date.
- Cost centres (`CostCenterResource`).
- Balance sheet and profit and loss accounts (`GeneralLedgerAccountResource`).
- Dimension groups (`DimensionGroupResource`) and dimension types (`DimensionTypeResource`).
- Currencies (`CurrencyResource`) and VAT codes (`VatCodeResource`).
- Bank books (`BankBookResource`) and cash books (`CashBookResource`).
- Classic fixed assets (`ClassicFixedAssetResource`) and asset methods (`AssetMethodResource`).
- Articles with sub-articles (`ArticleResource`).
- Budgets and budget vs. actual (`BudgetResource`).

#### Transactions

- Sales, purchase and journal transactions.
- Bank and cash transactions, with matching on detail lines.
- Reversal transactions (`ReversalTransactionResource`).
- Spread transactions (`SpreadTransactionResource`) and time entries (`TimeEntryResource`).
- Sales invoices (`SalesInvoiceResource`).
- Deleted transactions (`DeletedTransactionResource`).
- Transaction blocked values (`TransactionBlockedValueResource`).

#### Other services

- Electronic bank statements: read and create (`ElectronicBankStatementResource`).
- Browse data with `Twinfield::browse()` and `Twinfield::browseDefinitions()`.
- Declarations: VAT, ICT and tax group returns (`DeclarationResource`).
- Hierarchies (reporting structures) (`HierarchyResource`).
- Matching and undo match, and the matching audit trail (`MatchingResource`).
- Pay & Collect (`PayAndCollectResource`).
- XML web services (ProcessXml): send requests as string and read the raw response.

#### REST API

- `RestClient` with JSON reading and writing, the company GUID and paged searches.
- Ledger accounts (`LedgerAccountResource`).
- Posting rules (`PostingRuleResource`) and PSD2 bank feeds (`BankFeedResource`).
- The new fixed assets module (`FixedAssetResource`).
- Sales articles with `find()` and `save()` (`SalesArticleResource`).
- Budgets: lines, totals, spreading, import and export, and spreading templates (`SpreadingTemplateResource`).

[unreleased]: https://github.com/solvebeam/twinfield-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/solvebeam/twinfield-php/releases/tag/v0.1.0
