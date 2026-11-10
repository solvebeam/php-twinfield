---
title: Status per resource
description: Which resources are verified against live Twinfield, for reading and for writing, and which are only tested offline with fixtures.
---

Every resource has unit tests with fixtures: recorded Twinfield responses (anonymized) or, where no live response was available yet, responses that follow the documentation or the WSDL. A fixture that follows the documentation can be wrong where Twinfield differs from its documentation, so this page shows which parts are also verified against live Twinfield.

| Status | Meaning |
|---|---|
| **Live** | Run against live test companies with the SDK, and the responses parse. |
| **Partly** | Some methods or cases are verified live, others only offline; see the note. |
| **Offline** | Only tested with fixtures. The request follows the documentation, but Twinfield has not answered it yet. Expect differences in the response, and check the result in Twinfield the first time you use it. |
| — | Twinfield has no such operation. |

Writing is verified with the write tests in `tests/Write`, which change data in a test company and need an interactive confirmation (`composer test:write`). Open questions per resource are in [`TODO.md`](https://github.com/solvebeam/twinfield-php/blob/main/TODO.md); how live responses differ from the documentation is on [Differences with the documentation](/guides/documentation-differences/).

## Master data

| Resource | SDK | Read | Write | Note |
|---|---|---|---|---|
| [Activities](/guides/master-data/#activities) | `activities()` | Live | Offline | |
| [Articles](/guides/master-data/#articles) | `articles()` | Live | Offline | Save, delete and activate fixtures follow the documentation |
| [Asset methods (classic)](/guides/master-data/#asset-methods-classic) | `classicAssetMethods()` | Live | Offline | |
| [Bank books](/guides/master-data/#bank-books) | `bankBooks()` | Live | Offline | Twinfield can't delete a bank book |
| [Budgets](/guides/master-data/#budgets) | `budgets()` | Live | Offline | Writing is on the REST API, see below |
| [Cash books](/guides/master-data/#cash-books) | `cashBooks()` | Live | Offline | |
| [Cost centers](/guides/master-data/#cost-centers) | `costCenters()` | Live | Offline | |
| [Currencies](/guides/master-data/#currencies) | `currencies()` | Partly | Offline | The search is live; `get()` sends a `<currency>` request, so it only runs in the write tests |
| [Customers](/guides/master-data/#customers) | `customers()` | Live | Offline | |
| [Dimension groups](/guides/master-data/#dimension-groups) | `dimensionGroups()` | Live | Live | Including how deleting empties the group on the dimensions |
| [Dimension types](/guides/master-data/#dimension-types) | `dimensionTypes()` | Live | Offline | Only update; Twinfield has no create or delete |
| [Fixed assets (classic)](/guides/master-data/#fixed-assets-classic) | `classicFixedAssets()` | Live | Offline | Only assets "to be activated" seen live; active and sold assets only offline |
| [General ledger accounts](/guides/master-data/#general-ledger-accounts) | `balanceSheetAccounts()`, `profitAndLossAccounts()` | Live | Offline | Accounts with a VAT code or in a group only offline |
| [Offices](/guides/master-data/#offices) | `offices()` | Live | — | |
| [Projects](/guides/master-data/#projects) | `projects()` | Live | Offline | |
| [Rates](/guides/master-data/#rates) | `rates()` | Live | Offline | Time rates live; quantity rates only offline |
| [Suppliers](/guides/master-data/#suppliers) | `suppliers()` | Live | Offline | Suppliers paid by Twinfield (pay code, cheques) only offline |
| [Users](/guides/master-data/#users) | `users()` | Live | Offline | |
| [VAT codes](/guides/master-data/#vat-codes) | `vatCodes()` | Live | Offline | |

## Transactions

| Resource | SDK | Read | Write | Note |
|---|---|---|---|---|
| [Bank transactions](/guides/transactions/) | `bankTransactions()` | Partly | Offline | Only the not found response is live; reading an existing transaction only offline |
| [Cash transactions](/guides/transactions/) | `cashTransactions()` | Offline | Offline | Shares its code with bank transactions |
| [Deleted transactions](/guides/transactions/#deleted-transactions) | `deletedTransactions()` | Live | — | |
| [Electronic bank statements](/guides/transactions/#electronic-bank-statements) | `electronicBankStatements()` | Live | Offline | Reading an unposted statement only offline |
| [Journal transactions](/guides/transactions/#journal-transactions) | `journalTransactions()` | Live | Offline | |
| [Purchase transactions](/guides/transactions/#purchase-transactions) | `purchaseTransactions()` | Live | Offline | |
| [Reversal transactions](/guides/transactions/#reversal-transactions) | `reversalTransactions()` | Live | Offline | |
| [Sales invoices](/guides/transactions/#sales-invoices) | `salesInvoices()` | Live | Offline | `calculate()` only offline; the API can't delete a sales invoice |
| [Sales transactions](/guides/transactions/#sales-transactions) | `salesTransactions()` | Live | Offline | |
| [Spread transactions](/guides/transactions/#spread-transactions) | `spreadTransactions()` | Offline | Offline | |
| [Time entry](/guides/transactions/#time-entry) | `timeEntries()` | — | Offline | No read in the documentation |
| [Transaction blocked value](/guides/transactions/#blocked-values) | `transactionBlockedValues()` | — | Offline | |

## Other services

| Resource | SDK | Read | Write | Note |
|---|---|---|---|---|
| [Browse data](/guides/browse-data/) | `browse()`, `browseDefinitions()` | Live | — | A few browse codes only offline, see the guide |
| [Declarations](/guides/declarations/) | `declarations()` | Live | Offline | Writing: the status commands `setToSent()`, `setToApproved()` and `setToRejected()` |
| [Finder](/guides/finder/) | `finder()` | Live | — | |
| [Hierarchies](/guides/hierarchies/) | `hierarchies()` | Live | Offline | |
| [Matching](/guides/matching/) | `matching()` | Live | Offline | Reading is the audit trail of the REST API; `match()` and `undo()` only offline |
| [Pay & Collect](/guides/pay-and-collect/) | `payAndCollect()` | Partly | Offline | `list()` is live, but the test companies have no runs; `get()` only offline |
| [Periods](/guides/periods/) | `periods()` | Live | Offline | Writing: `changeName()`, closing and opening periods, years |

## REST API

See the [REST API guide](/guides/rest-api/#endpoints) for the status per endpoint.

| Resource | SDK | Read | Write | Note |
|---|---|---|---|---|
| [Budgets](/guides/rest-api/#endpoints) | `budgets()` | Live | Offline | |
| [Customer and supplier changes](/guides/rest-api/#endpoints) | `customers()->changes()`, `suppliers()->changes()` | Live | — | `customers()->changes()` gives HTTP 500 in some companies |
| [Feeds](/guides/rest-api/#endpoints) | `bankFeeds()` | Partly | — | Live, but the test companies have no PSD2 feeds |
| [Fixed assets](/guides/fixed-assets/) | `fixedAssets()` | Partly | — | Assets and classes live; a group and the reconciliation report only offline |
| [Ledger accounts](/guides/rest-api/#endpoints) | `ledgerAccounts()` | Live | — | |
| [Matching audit trail](/guides/matching/) | `matching()->auditTrail()` | Live | — | |
| [Posting rules](/guides/rest-api/#endpoints) | `postingRules()` | Live | — | |
| [Sales articles](/guides/rest-api/#endpoints) | `salesArticles()` | Live | Offline | |
| [Spreading templates](/guides/rest-api/#endpoints) | `spreadingTemplates()` | Partly | Offline | The default distribution is live; the test companies have no templates |
| [VAT codes](/guides/rest-api/#endpoints) | `vatCodes()->searchDetails()`, `getDetails()` | Live | — | |
