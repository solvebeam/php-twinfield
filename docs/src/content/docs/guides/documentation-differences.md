---
title: Differences with the Twinfield documentation
description: Where live Twinfield responses differ from the API documentation, how that was found, and what the SDK does about it.
---

The [Twinfield API documentation](https://developers.twinfield.com/documentation/api/) shows how requests and responses should look. Live responses don't always match it: elements are missing or extra, values are empty where the documentation shows a value, and some examples have mistakes. The SDK follows the live responses. This page records what was found, how it was checked, and how the SDK handles it, so you know why the code does what it does and what is still unverified.

Besides the online documentation, the repository has the service definitions in [`wsdl/`](https://github.com/solvebeam/php-twinfield/tree/main/wsdl) and old Twinfield web services manuals (PDF) in [`manuals/`](https://github.com/solvebeam/php-twinfield/tree/main/manuals). The manuals can be outdated, but sometimes explain an element that the online documentation leaves out.

The SDK never hides an unexpected response behind a default value. When a response doesn't match what is known, it throws an `UnexpectedResponseException` with the path of the element, e.g. `dimension/financials/meansofpayment is "creditcard", expected one of none, paymentfile, cheques.` Only behaviour that was seen in live responses is accepted.

## How differences are found

1. **Read live data, read-only.** Searches and reads on test companies, as many records as the rate limit allows. Twinfield answers HTTP 429 after a burst of requests, so long runs pause between requests.
2. **Compare the structure.** Every element and attribute path with the values it has, compared with the documented request and response.
3. **Parse everything.** Every live response is read with the SDK and written back with `edit()`, so a value the SDK can't handle shows up as an error.
4. **Record fixtures.** A few responses are recorded with `TWINFIELD_RECORD=1` and anonymized with `composer twinfield:anonymize`, so the unit tests use the real structure without real data.
5. **Write later.** Creating, updating and deleting need an interactive confirmation (`composer test:write`), so they are checked separately. Until then, open questions are in [`TODO.md`](https://github.com/solvebeam/php-twinfield/blob/main/TODO.md).

## Suppliers

[Suppliers](/guides/master-data/#suppliers) (`CRD`) were checked on 8 November 2026, read-only: all 184 suppliers of two test companies (7 in one, 177 in the other), each read and written back with `edit()`. Documentation: [Suppliers](https://developers.twinfield.com/documentation/api/master-data/suppliers/).

### Responses

| Documentation | Live responses | SDK |
|---|---|---|
| A posting rule line has a `ratio` (decimal) | All 15 lines seen have an empty `<ratio/>`: the line only proposes the account, cost center and VAT code | `PostingRuleLine::$ratio` is null for such a line, and `PostingRuleLine::create('4500', null)` creates one |
| The VAT code is a code | It can be `#`, the VAT code "Geen btw" (no VAT), in `financials/vatcode` and in posting rule lines | Accepted as a VAT code everywhere (`XmlValue::vatCode()`), also for general ledger accounts and fixed assets |
| – | A posting rule can hold a line with nothing in it: no account, ratio, VAT code or description | The empty line is left out of `PostingRule::$lines` |
| A bank account has `@id` and `@default` | Every bank account also has `@blocked` (always `false` so far) and `@destination` (always `0`); customers have them too | `Bank::$blocked` is read (null when left out, as in the documented response). `destination` is not read: its meaning is unknown |
| A bank account has `wayofpayment` and `payaddress` | Left out of every live response | `Bank::$wayOfPayment` and `$payAddress` are null when left out. `BankInput::wayOfPayment()` sends them only when set, and refuses a pay address without a cheque |
| `<sendtype>` and `<sendmail>` are listed under `<dimension>` | They are in `<remittanceadvice>`, which is in every read response | Read from `<remittanceadvice>` into `Supplier::$remittanceAdvice` |
| `collectmandate` and `collectionschema` are "not in use" | Both are returned: the mandate id is the supplier code, the schema `core` | Not read, and never sent |
| `ebilling`, `ebillmail`, `vattype`, `vatobligatory` and `performancetype` are "not in use" | Returned, always empty or `false` | Not read, and never sent |
| `<groups>`, `<postingrules>` and `<paymentconditions>` | Left out when empty (as for customers). No live supplier has groups or payment conditions | Empty lists in the read model, and not sent by `edit()` |
| – | Addresses have an extra `<ictcountrycode locked="true">` | Not read |
| `meansofpayment` can be `none`, `paymentfile` or `cheques` | Only `none` was seen: no test supplier is paid by Twinfield | All three are supported; `paymentfile` and `cheques` are only tested offline |

### Mistakes in the documentation examples

- The request structure has a typo: `<relationsreference>></relationsreference>`.
- The response example closes `<paymentcondition>` with a second opening `<paymentcondition>` instead of `</paymentcondition>`, so it is not valid XML.
- The response example leaves out the `<postingrules>` of the request it answers.
- The request example sends `<level>2</level>` in `<financials>`, which the field table calls read-only.

### Also affects customers

The posting rules, bank accounts and VAT codes are shared with [customers](/guides/master-data/#customers): `PostingRule`, `PostingRuleLine`, `Bank` and `BankInput` live in `SolveBeam\Twinfield\Dimension`. A customer with a posting rule line without ratio, or with the VAT code `#`, could not be read before; now it can.

### Not yet verified

These need a live write on a test company (`composer test:write -- --company=… --filter=Supplier`):

- Whether Twinfield accepts `cheques()` without a pay code, and a posting rule line without ratio.
- Whether `relationsreference` and a remittance advice by email stick.
- Whether `wayofpayment` and `payaddress` are accepted and returned, and what `destination` means.
- The shape of the save response. The fixture `supplier-save.xml` follows the documentation; `supplier-read-full.xml` (groups, payment conditions, credit transfer) is fictitious.

## Deleted transactions

Checked live (November 2026) on two test companies, one with 19 and one with 3819 deleted transactions, against the [documentation](https://developers.twinfield.com/documentation/api/transaction-data/deleted-transactions/) and the WSDL. The fields, types and fault codes match. The documentation leaves out:

- **Only the date counts.** `DateFrom` and `DateTo` are `xs:dateTime`, but Twinfield ignores the time and includes both days. `list()` sends the dates at midnight, so the request shows what Twinfield does.
- **A from date one day after the to date is accepted.** Twinfield gives an empty list then; from two days on it gives `InvalidDates` ("De 'van datum' moet ouder zijn dan de 'tot datum'."). `list()` refuses any from date after the to date before sending.
- **The company comes from the query, not the header.** With `CompanyCode` 1002_3 in the query and 1002 in the authentication header, Twinfield returns the transactions of 1002_3. `list()` always sends the company of the client.
- **Order and size.** The list is sorted by deletion date, newest first, and has no paging.
- **Values.** `DeletionDate` has milliseconds (`2025-09-03T11:55:10.883`), `TransactionDate` is midnight, `TransactionNumber` is an `xs:decimal` without fraction, `User` is the user code, and the day book code is not case sensitive. No field was empty or nil, so the SDK requires them all.

## Sales transactions

[Sales transactions](/guides/transactions/#sales-transactions) (`transaction`, day book `VRK`) were checked on 9 November 2026, read-only: 7 transactions of one test company (2015–2022; entered by hand and from sales invoices), each read with the SDK and turned into an input with `edit()`, and Finder `IVT`. Documentation: [Sales transactions](https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/).

### Responses

| Documentation | Live responses | SDK |
|---|---|---|
| No field is marked as mandatory | `invoicenumber` and `duedate` are in all 7 transactions | `SalesTransaction::$invoiceNumber` and `$dueDate` are required: a response without them throws |
| `paymentreference` | Left out of all 7 (none was set) | `$paymentReference` is null when left out |
| `matchstatus` of a VAT line is "always notmatchable"; the response example has no `matchstatus` on the VAT line | Every VAT line has `matchstatus` `available`, with `matchlevel` 1, `relation` 0 and a negative `basevalueopen` | `VatLine::$matchStatus` stays required |
| Open amounts on the total line: `valueopen`, `basevalueopen`, `repvalueopen` in the table; `openvalue`, `openbasevalue`, `openrepvalue` in the response example | Only `basevalueopen` | `InvoiceTotalLine` reads both names; `$openValue` and `$openRepValue` are null in the live responses |
| The response example has `customersupplier` on the total line | `relation`, as in the table | `InvoiceTotalLine::$relation` |
| Dimension attributes `name`, `shortname`, `dimensiontype` | As documented (the bank transaction documentation shows `type` instead) | Both are read |
| – | An extra header element `<regime>Generic</regime>` | Not read; the [journal transactions](#journal-transactions) documentation explains it |
| – | Empty `<freetext1/>`, `<freetext3/>` and `<originreference/>` | Null |

### Finder `IVT`

The documentation lists the search field and the options, not the columns. Live, the columns are: invoice number, amount, customer code, name and debit/credit (`D` or `C`); the names depend on the user's language.

- The results include open **purchase** invoices, with the supplier code in the customer column. Option `dim1` (the accounts receivable account) or `dim2` (a customer) leaves them out.
- The side is the opposite of the total line: a sales invoice (total `debit`) is `C`.
- The options `dim1`, `dim2`, `value` and `openvalue` work as documented.
- The amount is the open amount, not the original one: seen with a partly paid purchase invoice (see [Purchase transactions](#purchase-transactions)).
- The Finder gives no day book or transaction number.

`OpenInvoice` reads the columns by position and throws on anything else.

### Not yet verified

These need a live write on a test company (`composer test:write -- --company=… --filter=SalesTransaction`):

- The shape of the save, post, delete and change pay status responses. The fixtures follow the live read responses and the documentation, with fictitious values. The documented save response has no `matchstatus` on the VAT line: if a live save response leaves it out, `create()` throws after Twinfield saved the transaction.
- Whether a total line without `dim1` works, and whether `edit()` with the VAT lines of the response is accepted on update.
- Whether a missing `duedate` is accepted on create, and what Twinfield then sets.
- Whether `changepaystatus` works on a provisional transaction, or only on a final one.

## Purchase transactions

[Purchase transactions](/guides/transactions/#purchase-transactions) (`transaction`, day book `INK`) were checked on 9 November 2026, read-only: transaction numbers 1 to 5 of every year from 2014 to 2026 in two test companies. That gave 25 transactions (2016–2026; 18 imported, 7 entered by hand; 2 credit notes; 4 paid), each read with the SDK and turned into an input with `edit()`, and Finder `IVT` with `supplier()`. Documentation: [Purchase transactions](https://developers.twinfield.com/documentation/api/transaction-data/purchase-transactions/).

### Responses

| Documentation | Live responses | SDK |
|---|---|---|
| No field is marked as mandatory | `invoicenumber` in all 25; `duedate` in 24: one purchase invoice entered by hand has none | `PurchaseTransaction::$invoiceNumber` is required, `$dueDate` is null when left out |
| `paymentreference` | Left out of all 25 (none was set) | `$paymentReference` is null when left out |
| `value` of a detail line: "amount without VAT" | 5 imported invoices have discount lines with a **negative** amount on the debit side, e.g. `debit` `-11.72` with `vatvalue` `-2.46`; the VAT line then has the net VAT on the other side | `DetailLine` reads it; `edit()` keeps the negative amount, while `expense()` and `DetailLineInput::debit()` still refuse one |
| Open amounts on the total line: `valueopen`, `basevalueopen`, `repvalueopen` in the table; `openvalue`, `openbasevalue`, `openrepvalue` in the response example | `basevalueopen` (in 11 also `repvalueopen`), **signed**: negative for a purchase invoice (total `credit`), positive for a credit note; left out when the invoice is paid | `InvoiceTotalLine` reads both names; `$openBaseValue` keeps the sign |
| `matchdate` on the total line | Only on paid invoices (4 of 25) | `InvoiceTotalLine::$matchDate`, null when left out |
| `matchstatus` of a VAT line is "always notmatchable"; the response example has none | `available` (21 of 32, with `matchlevel` 1, `relation` 0 and `basevalueopen`) or `notmatchable` (11, e.g. a VAT line with 0.00 VAT) | `VatLine::$matchStatus` stays required |
| The response example has `customersupplier`, `openvalue`, and dimension attributes `type`, `inuse`, `vatcode`, `vatobligatory` | `relation`, `basevalueopen`, and dimension attributes `name`, `shortname`, `dimensiontype`, as for sales transactions | Both are read |
| – | An extra header element `<regime>Generic</regime>` | Not read, as for sales transactions |

### Finder `IVT`

The same Finder as for [sales transactions](#finder-ivt), with the same columns. For purchase invoices:

- The side is the opposite of the total line: a purchase invoice (total `credit`) is `D`, a purchase credit note `C`.
- The amount is the **open** amount, always positive: a purchase invoice of 15.00 with 10.00 still open is listed with 10.00. Paid invoices are left out.
- Option `dim2` with a supplier code works as documented.

### Mistakes in the documentation examples

- The request example books the purchase on account `8020` "Omzet (19%)" (revenue) and the response example shows the VAT line on "Te vord OB 19%" with VAT code `IH` without the `name` and `shortname` attributes that the other dimensions have.
- The info boxes use transaction type `PNV`, the XML example `INK`.

### Not yet verified

These need a live write on a test company (`composer test:write -- --company=… --filter=PurchaseTransaction`):

- The shape of the save, post, delete and change pay status responses. The fixtures follow the live read responses and the documentation, with fictitious values.
- Whether Twinfield accepts `edit()` of an invoice with negative discount lines on update.
- Whether a missing `duedate` is accepted on create, and what Twinfield then sets.
- Whether `changepaystatus` works on a provisional transaction, or only on a final one, and whether `freechar` `N` and `changepaystatus` are the same setting.

## Journal transactions

[Journal transactions](/guides/transactions/#journal-transactions) (`transaction`, day books `MEMO`, `BEGINBALANS`, `SALARIS`, …) were checked on 9 November 2026, read-only: transaction numbers 1 to 40 of every year from 2010 to 2026 in the journal day books of two test companies. That gave 83 transactions: 33 to read (31 `MEMO`, 2 `BEGINBALANS`; 6 provisional, 27 final; 2 with VAT lines) and 50 deleted ones. Each was read with the SDK and turned into an input with `edit()`. Documentation: [Journal transactions](https://developers.twinfield.com/documentation/api/transaction-data/journal-transactions/).

### Responses

| Documentation | Live responses | SDK |
|---|---|---|
| – | A **deleted** transaction is still returned, with `result="1"`, `location="deleted"`, the header and an empty `<lines />` | `get()` throws a `TransactionNotFoundException` with `deleted` true; `find()` returns null. This applies to all kinds of transactions. |
| `regime` only in the request | `<regime>` in every response: `Generic` (32) or `Fiscal` (1) | `JournalTransaction::$regime` is required |
| `@location` is described as "the destiny" | `temporary`, `final` or `deleted` | `TransactionHeader::$status` |
| Dimension attributes `name`, `shortname`, `dimensiontype`; the response example shows `type`, `inuse`, `vatcode`, `vatobligatory` | `name`, `shortname`, `dimensiontype` | Both are read |
| Open amounts `basevalueopen`, `repvalueopen` in the table; `openvalue`, `openbasevalue`, `openrepvalue` in the response example | `basevalueopen` and `repvalueopen`, negative for a credit line | `DetailLine` reads both names |
| The response example has `customersupplier` | `relation`, as in the table | `DetailLine::$relation` |
| `matchstatus` of a VAT line is "always notmatchable" | `notmatchable` or `available` (with `matchlevel` 1, `relation` 0 and a negative `basevalueopen`), as for sales transactions | `VatLine::$matchStatus` stays required |
| `invoicenumber` only when set | An empty `<invoicenumber/>` on every line | Null |
| – | Never a total line | A response with a total line throws |
| – | Period `00` for an opening balance, and periods `13` | `FinancialPeriod` |

How the lines balance is not documented. Live, the detail lines plus the VAT lines always balance; a detail line with a VAT code has `vatvalue`, and the VAT line Twinfield made for it has that amount on the same side. Without VAT lines in the input, the SDK therefore counts the VAT amount of a detail line on its side: that is what `balance()` checks.

### Mistakes in the documentation examples

- The request structure of a detail line has no `vatvalue`, `destoffice` or `performance*`, but the table describes them.
- The response example has `<transaction result="1" location="temporary">`; the root element description doesn't mention `result`.

### Not yet verified

These need a live write on a test company (`composer test:write -- --company=… --filter=JournalTransaction`):

- The shape of the save, post and delete responses. The fixtures follow the live read responses and the documentation, with fictitious values.
- Whether Twinfield accepts a detail line with a VAT code and `vatvalue` and makes the VAT line on the same side, as the SDK assumes; and what it does with a VAT code without `vatvalue` (the SDK refuses that).
- Whether `edit()` with the VAT lines of the response is accepted on update.
- Whether a booking outside period 0 in an opening balance day book is refused, and what `regime` does in a company with one regime.

## Sales invoices

[Sales invoices](/guides/transactions/#sales-invoices) (`salesinvoice`, Classic invoicing module) were checked on 9 November 2026, read-only: 40 invoices of type `FACTUUR` of one test company (2010–2011; 8 concept, 32 final), each read with the SDK and turned into an input with `edit()`, the not found response, Finder `INV`, and for a final invoice the sales transaction it refers to. A second test company and the invoice type `UREN` had no invoices. Documentation: [Sales invoices](https://developers.twinfield.com/documentation/api/transaction-data/sales-invoices/).

### Responses

| Documentation | Live responses | SDK |
|---|---|---|
| The structure shows `<transaction calculateonly="" raisewarning="">` as root, the table says "Element name: salesinvoice" | `<salesinvoice result="1">` | Root `salesinvoice` |
| "If the sales invoice does not exist an empty salesinvoice element is returned containing an error" | `<salesinvoice msgtype="error" msg="Factuur 999999999 van type FACTUUR bestaat niet." result="0"/>`, also for an unknown invoice type | `SalesInvoiceNotFoundException`, recognised by the structure (one error on the root, no `<header>`), not by the text |
| `financials` is "only present if the invoice has status final" | In all final invoices, in no concept invoice; `code` `VRK` and the number of a sales transaction that `salesTransactions()->get()` reads | `SalesInvoice::$transaction`; a final invoice without it, or a concept with it, throws |
| `status` can be `default` when reading | Only `concept` and `final` | `SalesInvoiceStatus::Default` is accepted when reading, refused when saving |
| `quantity` is an integer; the example has `2.00` | `1` and `0` | A numeric string, kept as Twinfield returns it |
| `unitspriceinc` is in the table, not in the structure | Only `unitspriceexcl` (the invoice type has prices excluding VAT) | Both are read; a line must have exactly one |
| `performancedate` on the header and `performancedate`, `performancetype` on the lines | Left out everywhere; empty on the VAT lines | Null when left out or empty |
| `freetext1`–`freetext3` are strings | Empty; `freetext1` and `freetext2` with empty attributes `name`, `shortname` and `dimensiontype` | Null when empty; the attributes are not read |
| `vatcode` of a line has `name` and `shortname` | Also `type="sales"` | The code is read |
| `dim1` of a line has `name` and `shortname` | No attributes | `DimensionReference` with name null |
| `vatcode` of a VAT line has `name`, `shortname` and `percentage` | Only `name` (an old name, `BTW 19%`) | The code and name are read, no percentage |
| `description` only on lines with article `0` or `-` | Also on the other lines: the name of the article | Read for every line; the input only sends it for `0` and `-` |
| – | `subarticle` on every line | Required |

### Mistakes in the documentation examples

- The request example sends `unitspriceexcl` `83.94` for a quantity of 2, and the response has `41.97`. Not checked: was the price of the article used, or the request price divided?
- The sub-article `501.01` in the example has a dot, which is not allowed in other codes. The SDK only checks the length of article and sub-article codes.

### Not yet verified

These need a live write on a test company (`composer test:write -- --company=… --filter=SalesInvoice`); the list is in [`TODO.md`](https://github.com/solvebeam/php-twinfield/blob/main/TODO.md):

- The shape of the save and `calculateonly` responses. The fixtures follow the documented response example. If a live save response leaves out an element that the read response has, `create()` throws after Twinfield saved the invoice.
- The one-off item `0` in a response: its `subarticle` and `description`.
- Whether an update keeps or removes the lines left out of the request, and how Twinfield handles a price for an article whose price can't be changed.
- Which header fields are really optional on create.

## Other resources

The differences for the other resources are in the [master data guide](/guides/master-data/), each in the section of the resource:

| Resource | Differences in short | Based on |
|---|---|---|
| [Customers](/guides/master-data/#customers) (`DEB`) | Empty lists left out; posting rules can be empty; an address can have no name; `<remittanceadvice>` is not documented as a root element | Live customers of the test companies |
| [Fixed assets](/guides/master-data/#fixed-assets) (`AST`) | Status in lower case; `@locked` on every element; empty method, periods and dates | One test company, assets "to be activated" only |
| [General ledger accounts](/guides/master-data/#general-ledger-accounts) (`BAS`, `PNL`) | Empty child validations and groups left out; VAT code without attributes; hidden accounts | About 230 accounts on two test companies |
| [Rates](/guides/master-data/#rates) | Open end date 2999-12-31; prices with four decimals; `unit` not as documented; Finder type `TRT` | 459 live time rates |
| [Offices](/guides/master-data/#offices) | The responses are not documented; booleans `True`/`False`; undocumented timeframe values | 247 live offices |
| [Users](/guides/master-data/#users) | Needs a company; `restricted` is in use; lower case `templatecompanyaccess`; `acceptExtraCost` in camel case; undocumented `wkemailaddress` | All 18 users of a live organisation |
| [VAT codes](/guides/master-data/#vat-codes) | No `office` in the read response; undocumented `discountaccount` and `writeoffaccount`; rates with nine decimals and negative shares; delete documented with `dimensiongroup` | 491 VAT codes of 12 live companies |
