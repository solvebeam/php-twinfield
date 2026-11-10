---
title: Differences with the Twinfield documentation
description: Where live Twinfield responses differ from the API documentation, how that was found, and what the SDK does about it.
---

The [Twinfield API documentation](https://developers.twinfield.com/documentation/api/) shows how requests and responses should look. Live responses don't always match it: elements are missing or extra, values are empty where the documentation shows a value, and some examples have mistakes. The SDK follows the live responses. This page records what was found, how it was checked, and how the SDK handles it, so you know why the code does what it does and what is still unverified.

Besides the online documentation, the repository has the SOAP service definitions in [`wsdl/`](https://github.com/solvebeam/twinfield-php/tree/main/wsdl), the REST API definitions (Swagger) in [`openapi/`](https://github.com/solvebeam/twinfield-php/tree/main/openapi) and [old Twinfield web services manuals](/reference/manuals/) (PDF) in `manuals/`. The manuals can be outdated, but sometimes explain an element that the online documentation leaves out.

The SDK never hides an unexpected response behind a default value. When a response doesn't match what is known, it throws an `UnexpectedResponseException` with the path of the element, e.g. `dimension/financials/meansofpayment is "creditcard", expected one of none, paymentfile, cheques.` Only behaviour that was seen in live responses is accepted.

## How differences are found

1. **Read live data, read-only.** Searches and reads on test companies, as many records as the rate limit allows. Twinfield answers HTTP 429 after a burst of requests, so long runs pause between requests.
2. **Compare the structure.** Every element and attribute path with the values it has, compared with the documented request and response.
3. **Parse everything.** Every live response is read with the SDK and written back with `edit()`, so a value the SDK can't handle shows up as an error.
4. **Record fixtures.** A few responses are recorded with `TWINFIELD_RECORD=1` and anonymized with `composer twinfield:anonymize`, so the unit tests use the real structure without real data.
5. **Write later.** Creating, updating and deleting need an interactive confirmation (`composer test:write`), so they are checked separately. Until then, open questions are in [`TODO.md`](https://github.com/solvebeam/twinfield-php/blob/main/TODO.md).

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

## Transaction blocked value

Checked against the [documentation](https://developers.twinfield.com/documentation/api/transaction-data/transaction-blocked-value/) and the WSDL (`wsdl/accounting/TransactionBlockedValueService.svc.wsdl.xml` and its `?xsd=xsd1`), not yet live. The fields, types and fault codes match.

- **Field order.** The WSDL has the fields in alphabetical order: `BlockedValue`, `CompanyCode`, `TransactionCode`, `TransactionLineId`, `TransactionNumber`. The raw envelope of `RegisterBlockedAmountForTransaction` in the documentation sends them in another order (`TransactionNumber` before `TransactionLineId`, `BlockedValue` last); the one of `UnregisterBlockedAmountForTransaction` follows the WSDL. WCF services refuse fields out of order, so the SDK sends the WSDL order.
- **Types.** `TransactionNumber` and `BlockedValue` are `xs:decimal`, `TransactionLineId` is `xs:int`. The SDK takes the number as `int` (transaction numbers have no fraction) and the value as `Amount`.
- **Not documented.** Which lines accept a blocked value, whether it may be negative, and how to read it back: the service has no query.

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
| – | Period `00` for an opening balance, and periods `13` | `Period` |

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

## Reversal transactions

[Reversal transactions](/guides/transactions/#reversal-transactions) (`reverse`) were checked on 9 November 2026, read-only: `getreversetransactioninfo` on 2 final and 1 deleted `MEMO` transaction, a sales (`VRK`) and a purchase (`INK`) transaction, and numbers that do not exist, in one test company. `postfinal`, `postprovisional` and `undo` change data and are not checked yet. Documentation: [Reversal transactions](https://developers.twinfield.com/documentation/api/transaction-data/reversal-transactions/).

### Responses

| Documentation | Live responses | SDK |
|---|---|---|
| The table names `@action` `getreversetransactioninfo`; the request example has no `action` | With `action="getreversetransactioninfo"` it works, and the response repeats the action. Without `action` it was not tried: it could be a write | `get()` always sends the action. The write guard only counts `<reverse action="getreversetransactioninfo">` as a read |
| `<reversed>` only "when there is reversed transaction" | Left out for transactions without reversal | `Reversal::$reversed` is null then |
| `<settings>` in every Get response | There, with `reverseinnextperiod` `false` for all transactions seen | `Reversal::$reverseInNextPeriod` is required |
| – | A sales or purchase transaction that exists gives the same error as a number that does not exist: `Originele transactie VRK:202600012 is niet gevonden.`, on `reverse/original` | Thrown as `ProcessXmlException`, not as `TransactionNotFoundException`: the text is the only difference and is in the language of the user |
| – | A **deleted** journal transaction is still found (`result="1"`, without `<reversed>`) | Returned as is; check `journalTransactions()->find()` when it matters |
| The introduction mentions detail and VAT lines | No response has lines, only office, code and number | `TransactionReference`; read the lines with `journalTransactions()` |
| `office` in the request | – | Always the company of the client (SDK choice); the response must be about the same transaction, or `UnexpectedResponseException` |

### Not yet verified

These need a live write on a test company (`composer test:write -- --company=… --filter=ReversalTransaction`, and the [Reverse journal transaction](/examples/reversal-transactions/reverse-journal-transaction/) example for `postfinal`):

- The shape of the `postfinal`, `postprovisional` and `undo` responses. The fixtures follow the documentation, with fictitious values.
- How `reverseinnextperiod` is set: the requests have no settings. Perhaps a setting of the day book.
- What `postfinal` does on a provisional transaction, on a transaction that is already reversed, and `postprovisional` on a final one.
- Whether a provisional transaction with reversal settings gets its reversed transaction when it is posted.

## Spread transactions

[Spread transactions](/guides/transactions/#spread-transactions) (`spread`) are not checked live yet: the SDK and its fixtures follow the documentation, with fictitious values. Documentation: [Spread transactions](https://developers.twinfield.com/documentation/api/transaction-data/spread-transactions/).

### Documentation

| Documentation | SDK |
|---|---|
| `settings/dim1`: "Dimension with level 1" | `SpreadSettings::$intermediateAccount`: the introduction calls it the intermediate account the amounts are spread from |
| Get: `<settings>` has either `startperiod` and `endperiod`, or `linessettings` | `SpreadSettings` has either both periods or `lines`; `isByLines()` tells which |
| Get: the element of one line is under the heading "Linessettings" twice | Read as `linessettings/linesettings`, as in the examples and the other actions |
| The response examples have a spread transaction with number `20210003`, between `202100002` and `202100004` | Taken as a typing error; any number from 1 is accepted |
| "If all periods of line spread settings are equal to transaction period then undo action will be triggered", without a response example | `postFinalByLines()` and `postProvisionalByLines()` accept a response without `<settings>` and `<spreadtransactions>`: `Spread::$settings` is null and `isSpread()` false |
| Delete (`undo`): the response has only `<original>` | `undo()` returns nothing |
| `office` in the request | Always the company of the client (SDK choice); the response must be about the same transaction, or `UnexpectedResponseException` |
| Get has an `action`, `getspreadtransactioninfo`; every other action changes data | The write guard of the tests and examples only counts `<spread action="getspreadtransactioninfo">` as a read |

### Not yet verified

`get()` needs a live read (`composer test:live -- --filter=SpreadTransaction`); the rest a live write on a test company (`composer test:write -- --company=… --filter=SpreadTransaction`, and the [Spread purchase transaction](/examples/spread-transactions/spread-purchase-transaction/) example for `postfinal`):

- What `getspreadtransactioninfo` answers for a transaction that is not spread: without `<settings>` and `<spreadtransactions>` (as the SDK assumes), or with empty elements.
- The errors for a journal transaction, a number that does not exist and a deleted transaction.
- Whether `postprovisional` already returns spread transactions, or only saves the settings until the transaction is posted.
- The response of a spread by lines that removes the spread.
- Whether `undo` is refused for a final transaction: the documentation only names a provisional one.
- The day book of the spread transactions (`KOSTENVERDELING` in the examples) and whether `journalTransactions()` reads them.
- Whether the intermediate account must be a balance sheet account, and which periods Twinfield refuses (closed, before the transaction).

## Time entry

[Time entry](/guides/transactions/#time-entry) (`teqs`) is not checked live yet: the SDK and its fixtures follow the documentation, with fictitious values. Documentation: [Time entry](https://developers.twinfield.com/documentation/api/transaction-data/time/).

### Documentation

| Documentation | SDK |
|---|---|
| `duration`: "The duration entered in hours. No fractions are allowed." The example sends `240` and gets `internal="240.00"` with a rate of `15.00` per `15.00` units, which fits minutes | `TimeLineInput::$duration` is a whole number from 1, sent as is; the unit is not named |
| "If the journal transaction is not found it will be automatically created, otherwise it is updated", but a time entry has no number | `save()` only; there is no separate create and update |
| "Multiple transactions can be created at once by enclosing them by the transactions element"; the root in the structure and the example is `<teqs>` | `saveMany()` sends `<teqs>` with a `<teq>` per entry |
| `@internal`, `@external`, `@units`, `@name` and `@shortname` have type "integer"; the example has `240.00`, `15.00` and names | The values of a line are an `Amount`, the prices of a rate a `UnitPrice`, `units` a numeric string and the names strings |
| `value1`: "double" | `QuantityLineInput::$quantity` and `QuantityLine::$quantity` are numeric strings, never floats |
| `prj1` and `prj2` are in every example; nothing says whether they are required | Required for `TimeEntryInput::direct()`, optional for `indirect()` and `personal()`; `TimeEntry::$project`, `$activity`, `$customer` and `$authoriser` are null when the response leaves them out |
| `header/code` is `DIRECT`, `INDIRECT` or `PERSONAL` | `TimeEntryType` |
| `prj1`, `prj2`, `accountsreceivable` | `project`, `activity` and `customer` |
| No read and no delete | Not in the SDK; read time entries with browse data |

### Not yet verified

These need a live write on a test company with time and expenses (`composer test:write -- --company=… --filter=TimeEntry`, and the [Create time entry](/examples/time-entries/create-time-entry/) example with `--save`). A saved time entry stays: there is no delete.

- The unit of `duration`.
- What makes Twinfield update an entry instead of creating one, and whether a second save replaces the lines or adds them.
- Which header fields are required per type, and what the response has for an entry without project.
- Whether `billable`, `ratecode` and `currency` are in every line of the response, as the SDK requires.
- The `result` per `<teq>` when one of several fails, and where the error is.
- Whether an undocumented read (`<read><type>teq</type>`) or delete exists.

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

These need a live write on a test company (`composer test:write -- --company=… --filter=SalesInvoice`); the list is in [`TODO.md`](https://github.com/solvebeam/twinfield-php/blob/main/TODO.md):

- The shape of the save and `calculateonly` responses. The fixtures follow the documented response example. If a live save response leaves out an element that the read response has, `create()` throws after Twinfield saved the invoice.
- The one-off item `0` in a response: its `subarticle` and `description`.
- Whether an update keeps or removes the lines left out of the request, and how Twinfield handles a price for an article whose price can't be changed.
- Which header fields are really optional on create.

## Browse data

[Browse data](/guides/browse-data/) was checked on 9 November 2026, read-only, on a test company with 603 transaction lines and with a query over 216,710 lines: the definitions of all 38 browse codes, the browse fields, and about 40 queries on 030_3, 040_1 and 130_3. Documentation: [Browse data](https://developers.twinfield.com/documentation/api/request-data/browse-data/), and the old [browse manual](/reference/manuals/) (version 5.4, 2009).

### Requests

| Documentation | Live responses | SDK |
|---|---|---|
| The example request sends `label`, `visible` and `ask` for every column | Only `field` is needed. Without `<visible>` (default `false`) a column only selects and is not in the result; `label` is empty in the result when not sent | `columns()` sends `<visible>true</visible>`, criteria on other fields are sent without it. `label` and `ask` are never sent |
| Don't change `visible` from `false` to `true` | `fin.trs.head.yearperiod` (not visible in 030_3) as column gives a SOAP server error; `fin.trs.head.modified` (also not visible) works | Documented; the SDK sends what you ask |
| Operators `none`, `equal` and `between` (old manual: also `lessthan`) | `notbetween` works; `like` works with `*` and `?` and is used in the definitions of 130 and 230; `lessthan`, and `in` and `notin` (named in an error message), give a server error. `between` without `to` is refused, `equal` ignores `to` | `equal()`, `between()`, `notBetween()`, `like()`; `BrowseOperator` has `none`, `equal`, `like`, `between` and `notbetween` |
| Up to three sort fields | A fourth gives a server error. 000 sorts, 030_3 ignores the sort fields | `sortBy()` refuses a fourth field; the limitation is documented |
| The data is that of the company the user is logged in to | It comes from the company in the SOAP header, but a criterion `fin.trs.head.office` with another company returns the data of that company | `BrowseQuery` refuses a `fin.trs.head.office` criterion for another company; `BrowseRow` checks the company of every row |
| The definition proposes criteria, e.g. status `normal` | They are not applied: without a status criterion the result has all statuses, also drafts | Documented; examples always send a status criterion |
| 400 and 410 have the cash or bank book (`fin.trs.head.bankcode`) as criterion | Without it Twinfield answers with a SOAP server error | Documented in the examples, which always send it |
| The list of browse codes has no 161; the BrowseCodeDetails overview has "161 Credit management" | Twinfield has a definition for 161 ("Kredietbeheer", 15 fields: open items of customers) and answers queries on it | No `BrowseCode` case; `browse('161')` works. Mentioned on [Browse codes](/guides/browse-codes/) |

### Responses

| Documentation | Live responses | SDK |
|---|---|---|
| `optimize` removes `field`, `hideforuser` and `type` from all `<td>` except those in `<th>` (old manual: "in the first `<tr>`") | As documented: `<th>` keeps them, rows don't. Rows keep the `name` attribute | Always `optimize="true"` (about three times smaller); cells are read in the order of `<th>` |
| Cells have a value | Cells of dropdown fields and dates also have a `name` with the value as shown to the user, e.g. `name="Definitief"` for `final`, `name="25/02/2026"` for `20260225` | `BrowseRow::displayName()` |
| Types `String`, `Decimal` and `Value` | Also `Number` (year, period), `Date` (`yyyymmdd`) and `Datetime` (`yyyymmddhhmmss`) | `BrowseValueType`; an unknown type throws |
| – | Draft transactions have transaction number `-1` | `TransactionLineReference` accepts `-1` (`isDraft()`) |
| – | Browse codes with totals (040_1) have a `<key>` with only `<office>` | `BrowseRow::$transactionLine` is null |
| `first`, `last` and `total` | Always all rows (`first` 1, `last` = `total`), also for 216,710 lines (25 MB). An empty result has `0`, `0`, `0` | `BrowseResult` throws when rows are missing |
| `<browsefields>` | Has no `result` attribute, unlike other ProcessXml responses. `canorder` is always empty | `fields()` reads it without `ProcessXmlResult`; `canorder` is not read |
| Errors | `<browse result="0" msg="…">` in the language of the user, e.g. `Veld fin.trs.nope bestaat niet.`, `Opvraagcode 999 bestaat niet.` | `ProcessXmlException` |

### Found in the SDK

Responses over 10 MB lost data: libxml drops text over 10 MB without `LIBXML_PARSEHUGE`, so a large `ProcessXmlStringResult` was cut off without error. `SoapResponse` and `ProcessXmlResult` now parse with `LIBXML_PARSEHUGE`. This affected every service, not only browse data.

### Not yet verified

- The value types and formats of 031, 031_2, 690 and the Time & Expenses codes (610_1, 650_1, 651_1, 652_1, 660_1, 661_1, 662_1): the test company has no data for them, so their examples and fixtures follow the definitions. An unknown type throws an `UnexpectedResponseException`. The other browse codes were queried live on 10 November 2026 with their example.
- How Twinfield behaves with very large queries (timeouts, limits): 216,710 lines took 34 seconds.

## Electronic bank statements (reading)

Checked live (November 2026) on a company with bank statements downloaded by Twinfield (14 statements, 182 lines, 224 allocations in two weeks), against the [documentation](https://developers.twinfield.com/documentation/api/request-data/electronic-bank-statements/) and the WSDL (`BankStatementService.svc?singleWsdl`). The documentation has no response example.

### Mistakes in the documentation

- **`AllocationRuleStorageLevel`.** The documentation gives the type `BankStatementAllocationRuleStorageLevel?` (nullable) but does not define it; it defines a `BankStatementAllocationRuleType` with `LocalBank`, `LocalOffice`, `TemplateBank` and `TemplateOffice` instead. The WSDL has `BankStatementAllocationRuleStorageLevel`, not nillable, with also `NotStored`. Live every line has it: `NotStored` or `LocalOffice`. The SDK follows the WSDL (`BankStatementAllocationRuleStorageLevel`).
- **Fault codes.** Not documented; the WSDL has `BankStatementServiceFault` with only `MethodIsDisabled` (not seen live).

### Requests

- **`StatementDateFrom` and `StatementDateTo` are required.** Without them Twinfield answers with a server error ("Er is een fout opgetreden bij de server"), not a validation fault.
- **The time counts.** Statements are dated at midnight and Twinfield compares date and time: from and to `2026-11-03T12:00:00` gives nothing, `2026-11-03T00:00:00` gives the statement of 3 November. `list()` sends the dates at midnight, so both days are included.
- **From after to is accepted** and gives an empty list. The SDK refuses it before sending.
- **`IncludePostedStatements`** defaults to `false` in Twinfield. Statements that Twinfield processes automatically are posted right away, so the SDK sends `true` unless you pass `includePosted: false`.
- **No paging.** A long period with many statements gave an HTTP 502 or a timeout; two weeks (14 statements) took a few seconds. `GetBankStatementsByCreationDate` with only a to date gives every statement up to then, in no clear order.
- `GetBankStatementsByCreationDate` with only a from date works. Whether the time of the creation dates counts could not be checked: the response has no creation date. The SDK sends it as given and refuses a call without dates.

### Responses

- **Amounts have four decimals** (`4843.9600`), never other than zeros after the second. The SDK reads them as `Amount` and throws when the third or fourth decimal is not zero.
- **Line amounts are signed** (`-10.3000` for money paid); allocation amounts are positive with a `Sense`. Money received is allocated `Credit`, so the allocations with credit as positive add up to the line amount. This held for every line; the opening balance plus the lines was the closing balance for every statement.
- **Empty strings, not nil.** `ContraAccountNumber`, `PaymentReference`, `ReturnReason`, `EndToEndId` and `Dimension2` to `Dimension6` come as empty elements; `ChequeNumber`, `DescriptionFormat` and `Recognition` as nil. The SDK gives null for both.
- **Lists are always there**, empty when there is nothing (`<b:AllocationMethods/>`, `<b:Matchings/>`, `<b:VatLines/>`), not left out or nil.
- **`AllocationMethods`** are filled when a rule was used (`Recognition` `AllocationRule`); for types other than `Amount` the `Amount` and `AmountOperator` are nil.
- **`Matching.Number`** is an `xs:decimal` without fraction (`202601319`); `BaseValue` has the sign of the matched line (negative for a purchase invoice).
- `DescriptionFormat` can hold the placeholder `[editable_description]`.

### Not yet verified

- A statement that is not posted (`TransactionNumber` nil): all statements of the company were posted. Unit tested with a fictitious response.
- `VatLines`, `ChequeNumber`, `PayingInSlipNumber` and an allocation rule on the amount: never filled live. Modelled after the WSDL and unit tested with a fictitious response.
- When Twinfield gives `MethodIsDisabled`.

## Declarations

Checked live (November 2026), read-only, on an organisation with 5,828 returns of all five types (2010–2026), against the [documentation](https://developers.twinfield.com/documentation/api/miscellaneous/declarations/) and the WSDL (`declarations.asmx?wsdl`, the same as in `wsdl/accounting/`). The SDK follows the WSDL and the live responses. See the [declarations guide](/guides/declarations/).

### Mistakes in the documentation

- **Kind of service.** The documentation calls it a "Command & Query Web Service"; it is a classic `.asmx` service with a `Header` (`AccessToken`, `CompanyCode`) and a SOAPAction per operation. The raw envelopes in the documentation are right.
- **Element names.** On the wire they are camelCase (`companyCode`, `documentId`, `isMessageIdRequired`); the documentation mixes `DocumentId` and `documentId`.
- **`DocumentTimeFrame`** also has `Year` (int), filled in every return; the documentation only lists `Period`.
- **The list of returns** is `vatReturn/DeclarationSummary` (an array), next to `<Operation>Result` with the messages; the documentation shows one `DeclarationSummary VatReturn`.
- **Messages** have `Type`, `Text`, `Code` and `Parameters` (a list of `string`, which can be nil), not `TypeField`, `TextField`, `CodeField` and `ParametersField` (a string).
- **`LoadMessage`** also has `ParameterIsRequired` and `ParametersAreRequired` (WSDL); Twinfield gives `ParameterIsRequired` without `modifiedSince`.
- **`startIndex` is 0-based.** The documentation's examples use `1`, which skips the first return.
- **`modifiedSince` is required**, as in the WSDL (`minOccurs="1"`). The documentation says "If provided" for `GetRangeOfSummariesSinceModified`; without it Twinfield answers `ParameterIsRequired`.
- **`*` as company code** works for `GetAllSummaries` and `GetRangeOfSummaries` too, not only for the two operations where the documentation mentions it. `GetNumberOfDeclarations` refuses it with `InvalidCode`, so the SDK has no organisation count.
- **The company in the header is required**, also with `*`: without it Twinfield answers SOAP fault 359 ("Company ontbreekt in request header").
- **`SetToRejected.reason`** is "Mandatory" in the documentation and optional in the WSDL. The SDK requires it.

### Requests

- **`modifiedSince`** counts to the second, at or after the moment ("on and after"). Twinfield respects the time zone (`11:07:46+01:00` is `10:07:46Z`) and reads a time without one as UTC. The SDK sends the time zone of the date.
- **No maximum page size.** `count=100000` gave all 5,828 returns.
- A negative `startIndex` gives `InvalidParameter`; `count=0` gives an empty list. The SDK refuses both before sending.
- **Only the document ID counts** for downloads and payment references: the company in the header can be another company of the organisation.
- **`isMessageIdRequired`** made no difference: `MessageReferenceSupplierVAT` was filled in with `false` and with `true`, for an approved and for a created return.
- **The type is not checked** for downloads: `GetVatReturnAsXbrl` on an ICT return gives the ICT return. Payment references are checked (`InvalidDocumentType`).

### Responses

- **Order.** Returns come by ID, oldest first.
- **`ExtraInformation`** is an empty element in every return, and left out in the four oldest (2010, 2011). The SDK gives null for both.
- **`Assignee` and `Company`** have a code and a name in every return.
- **Status.** Only `1` (`Gecreëerd`) and `7` (`Goedgekeurd`) were seen; the description is in the language of the user.
- **`vatReturn`** of the XBRL and XML downloads holds one element: `xbrli:xbrl`, `AangifteOmzetbelasting` (with `xmlns=""`) or `OpgaafICP`. The SDK returns it as a standalone document.
- **Empty payment reference.** `<paymentReference/>` for every yearly supplementary return (`YEARLYSUPPLETION`) and the oldest VAT return; the SDK gives null. For an ICT return and a yearly supplementary tax group return (`TXGRPYRSP`) Twinfield answers `InvalidDocumentType`.
- **An unknown document ID** is a SOAP fault with code `100` and a server error message, not `DocumentDoesNotExist`.
- **An unknown company code** gives an empty list, not `InvalidCode`.

### Not yet verified

- The status commands (`SetToSent`, `SetToApproved`, `SetToRejected`): their response and messages, and which transitions Twinfield allows. Only tested offline, with responses after the WSDL.
- Messages of type `Warning` or `Informational`: never seen. The SDK only throws on `Error`.
- Statuses other than `Created` and `Approved`, and filled `ExtraInformation` (e.g. for `Error while sending`).

## Hierarchies

Checked live on 10 November 2026, read-only: Load of the four reporting structures of an organisation (up to 3,858 nodes and 2,949 accounts) for three companies, an unknown and an empty code, and the Finder types `HIE` and `HND`. Documentation: [Hierarchy](https://developers.twinfield.com/documentation/api/miscellaneous/hierarchy/) and the WSDL (`hierarchies.asmx?wsdl`, the same as in `wsdl/accounting/`). The SDK follows the WSDL and the live responses. See the [hierarchies guide](/guides/hierarchies/).

### Mistakes in the documentation

| Documentation | WSDL and live responses | SDK |
|---|---|---|
| `Hierarchy.Rootnode` | `RootNode`, as in the Save example of the documentation | `Hierarchy::$rootNode` |
| `LoadResponse` has `String LoadResult[]` and `Hierarchy hierarchy[]` | `LoadResult` is a list of `MessageOfHierarchyLoadMessage`; `hierarchy` is one reporting structure, left out on an error | `get()` returns one `Hierarchy` |
| `AccessRights` is one `HierarchyAccess` in the Load response, a list in Save | A list in both (`ArrayOfHierarchyAccess`) | `list<HierarchyAccess>` |
| `HierarchyAccess.Messages` is `String[]` | `ArrayOfMessageOfHierarchyAccessMessage`, with codes like the other messages | `HierarchyAccessMessage` |
| Message codes only for Save | Every operation has codes in the WSDL: Load, Hide and Delete `InvalidCode` and `UserRoleNotAllowedToPerformThisAction`; Bind `BindingConflict`, `BindingNotUnique`, `InvalidNode`, `ReadOnly`, `BindingInvalid`; Unbind `InvalidNode`; nodes and access rights their own lists | An enum per list, all `HierarchyMessageCode` |
| Load, Bind, Unbind, Save and Hide | The WSDL also has `Delete` (`hierarchyCode`), answered with the messages of Load | `delete()` |
| `HierarchyNode.Id` is an `Int` | `nillable` in the WSDL, for a new node | `HierarchyNodeInput::$id` is null for a new node and left out of the request |

### Requests

- **The company in the header is required**: without it Twinfield answers SOAP fault 359 ("Company ontbreekt in request header"). The SDK throws a `LogicException` before sending.
- **Accounts per company.** The nodes are the same for every company; the accounts in the Load response are those of the company in the header (2,949, 3,146 and 2,489 accounts for the same reporting structure in three companies). Bind and Unbind have an `office` parameter; the SDK sends the company of the client.
- **An unknown or empty code** gives an `Error` message `InvalidCode` with the code as parameter ("De rapportagestructuur X bestaat niet."), not a SOAP fault. The SDK throws a `HierarchyNotFoundException`.

### Responses

- **Every element is there** in every node: `Id`, `Code`, `Name`, `Description`, `Accounts`, `ChildNodes`, `Messages` and `Touched`, also when empty. `Description` is an empty element in most nodes; the SDK gives null.
- **Node codes are unique** within a reporting structure; the root node has the code and name of the reporting structure.
- **A node has either accounts or child nodes**, never both, in all 4,558 nodes.
- **`BalanceType`** is `Balance`, `Debit` or `Credit`. The 49 accounts with `Debit` were each also bound with `Credit`, to the same or another node.
- **`Messages`** of nodes and access rights are always empty in a Load response.
- **Access rights**: one per user (`UserCode`, `AccessRightTabAccesible` `true`) and a last one for everyone else (all codes empty, `false`), with `Type` `Edit`, `Bind`, `Use` or `None`. `OfficeCode` and `RoleCode` were always empty elements; the SDK gives null for an empty code.
- **`HIE`** gives the same reporting structures in every company; with `company=*` there are no extra company columns. `HierarchySummary` has the organisation, not a company.
- **`HND`** with option `hierarchy` gives every node of the reporting structure, the root node included, with code and name, but not in tree order, nor by code or ID (for one reporting structure the root node came first, for another one 320th). There is no node ID.

### Not yet verified

- Save, Bind, Unbind, Hide and Delete: their responses and messages. Only tested offline, with responses after the WSDL. Open questions: does Save accept a new node without `Id`; what does Twinfield do with `AccessRightTabAccesible` in a request (the SDK sends `true` for an access right of a user, role or company, `false` otherwise); is an empty list of access rights accepted; can a hidden reporting structure be shown again; does Save return the IDs of new nodes.
- Messages of type `Warning` or `Informational`: never seen. The SDK only throws on `Error`.
- Access rights for a role or a company.

## Matching

Checked on 10 November 2026 against the documentation ([Matching](https://developers.twinfield.com/documentation/api/miscellaneous/matching/), [Undo match](https://developers.twinfield.com/documentation/api/miscellaneous/undo-match/)) and the WSDL (`matching.asmx?wsdl`, in `wsdl/accounting/`), not yet live: matching changes data. The two test companies have no matched customer lines in browse data, so no match number was seen yet. See the [matching guide](/guides/matching/).

### Requests

- **`<match>` follows the documentation**: `set` with `office`, `matchcode`, `matchdate` and `lines/line` with `transcode`, `transnumber`, `transline` and optionally `matchvalue` and `writeoff` with `@type`. The unit tests compare the request with the three examples of the documentation.
- **`matching.asmx` has only `UndoMatch`**: matching itself is not a SOAP operation, only the XML web services request.
- **The dimension type of `UndoMatch`** is "the dimension type of the dimension", without a list. The SDK takes a `DimensionTypeCode` or a string; `MatchCode::dimensionType()` maps the match codes to `BAS`, `DEB`, `CRD` and `PRJ`.

### Responses

- **The `<match>` response is not documented.** The SDK expects a `<match>` root element and throws a `ProcessXmlException` on `result="0"`, as for the other XML web services; `match()` returns nothing. The fixture `matching-match.xml` is fictitious: it echoes the request with `result="1"`.
- **`UndoMatchResult`** is a list of `MessageOfUndoSetByTypeResult` (`Type`, `Text`, `Code`, `Parameters`), as in the WSDL. The fixtures are fictitious and follow the WSDL and the hierarchies service: an empty `<UndoMatchResult/>` on success, `<Parameters/>` in every message.

### Not yet verified

- The shape of the `<match>` response: does it give a match number or a status? Then `match()` should return it.
- Which errors Twinfield gives for a line that is already matched, a wrong line, a match value above the open amount, a `writeoff` without `@type`; whether `matchdate` is required; whether a provisional transaction can be matched.
- The `UndoMatch` response on success (empty, or left out?) and its messages; whether `Parameters` is always there.
- Match codes `070` and `370`, and `writeoff` types `currency` and `writeoff`.

## Pay & Collect

Checked on 10 November 2026 against the documentation ([Pay & Collect](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/)), the WSDL (`payandcollect.asmx?wsdl`, in `wsdl/accounting/`) and the manual (`Webservices-Manual-Pay_Collect.pdf`, version 5.4, 2010). Live, read-only: `GetRuns` on two test companies returns an empty `<GetRunsResult/>`, and the Finder (`PAY`) gives pay types `SEPANLCT` and `SEPANLDD`. The other operations change data and are not checked live yet. See the [Pay & Collect guide](/guides/pay-and-collect/).

### Mistakes in the documentation

- **`RunType` is not documented.** It is used in `PayOverview` and `PayRun`; the WSDL and the manual have `Payment` and `DirectDebit`.
- **`SummaryInfo.Details`** is documented as one `DetailInfo`; the WSDL has a list (`ArrayOfDetailInfo`). **`RelationBank`** is documented as `String Bank`; the WSDL has the complex type `Bank`.
- **`DetailInfo.DiscountAmount`** is in the WSDL (and in `DetailSettings` of Modify), not in the documentation of `DetailInfo` or in the manual.
- **`touched` and `modified` of a response** are documented in the message (`MessageOfModifyResult`, …); in the WSDL they are elements of the response itself (`ModifyResponse/touched`). **`FinishResponse`** has no `touched` and `modified`, though the documentation shows them.
- **The CreateRun responses** contain the new run (`payRun`), which the documentation leaves out; the manual has it as `out PayRun payRun`.
- **CreateRunAsync** takes `createRunAsyncRequest`; the documentation calls it `createRunRequest`.
- **Finish** takes the pay type as `payRunCode`, the other operations as `payType`.
- **The conditions** use values that are not in the enums: Modify `Status <> Busy`, Finish `Phase < Posting` and `Status = Error`. The enums have phase `Post` and status `Started`, `Done` and `DoneWithErrors`.
- **The CreateRun parameters** `DimensionFrom` and `DimensionTo` are `relationFrom` and `relationTo` in the manual.

### Requests

- **CreateRun2 instead of CreateRun.** CreateRun2 has the same fields plus `DiscountDaysTolerance` (`minOccurs="1"`: the SDK sends 0 by default). `Method`, `DirectRemove` and `MaxDuedate` are nillable: the SDK leaves them out when they are not set, so the pay type decides.
- **The period** is a string in the create request (`2026/11`, as in the manual); the run has `Year` and `Period` as numbers. The SDK uses `Period` for both.
- **Custom and export settings** are an array of string arrays. The manual shows `[name, value]` pairs (`["PayCostNational", "0"]`, `["FileType", "production"]`); the SDK takes `[name => value]`. The only documented setting is `SEPANLDDcollectionschema` (0 Core, 1 B2B).
- **Modify sends every summary and detail.** `SummarySettings` has `Relation`, `Currency` and `Id` as key; `DetailSettings` requires `Value` and `Included`. The SDK sends the whole run with the current values for what did not change.
- **`modified`** is sent back exactly as Twinfield returned it.
- **CreateFiles instead of CreateFile.** CreateFile gives one file; CreateFiles all, when a bank limits the number of transactions per file.

### Responses

The fixtures are fictitious and follow the WSDL, except the empty `GetRuns` response, which is live.

- **The message code** is always `UnknownTextualMessage` (`CreateRunResult`, `ModifyResult`, …), so the SDK keeps it as a string and does not have an enum per operation.
- **Dates** are `xs:dateTime`. Pay date, invoice date and due date are read as dates.
- **`payFile`/`payFiles`** are `base64Binary`; the SDK decodes them.

### Not yet verified

- Every response except an empty `GetRuns`: the shape of `PayOverview`, `PayRun` and the messages, which fields Twinfield leaves out or sends empty (`User`, `Bank`, `Reference`, `RelationBank`, `InvoiceNumber`), and how many decimals the amounts have.
- Whether the CreateRunAsync response contains the run; `createAsync()` returns only the messages now.
- Whether Modify needs every summary, or only the changed ones; whether a value above the original value or a negative discount gives an error message.
- The error for an outdated version, and for a step in the wrong phase.
- What `GetRunInfo` gives for an unknown run.
- Post, CreateFiles and Finish after posting: the write test never posts, because it can't be undone.

## Periods

Checked on 10 November 2026 against the documentation ([Periods](https://developers.twinfield.com/documentation/api/miscellaneous/periods/)) and the WSDL (`PeriodService.svc?wsdl`, in `wsdl/accounting/`). Live, read-only: `GetYears` and `GetPeriods` for all 61 years of one test company. The commands change data and are not checked live yet, except `ChangeName` in `composer test:write`. See the [Periods guide](/guides/periods/).

### Mistakes in the documentation

- **`GetYearsResult.Years`** is documented as `int`; the comment says "Array of all years", and the response is a list of ints.
- **The `Period` data contract is not documented.** The GetPeriods example uses `period.Period` and `period.IsOpen`; the response has `Number` and `Open`, next to `Name` and `EndDate`. The example also loops over `result` instead of `result.Periods`.
- **The ChangeEndDate example** sends `<a:PeriodNumber>02</a:PeriodNumber>` for an `int`; the SDK sends `2`.
- **Field order.** The C# classes list `Year`, `PeriodNumber`, then `EndDate` or `Name`; the example envelopes send them in alphabetical order, as in the WSDL. The SDK sends the WSDL order.

### Responses

| Documentation | Live responses | SDK |
|---|---|---|
| `EndDate` is a `DateTime` | `i:nil="true"` for the special periods 0 (opening balance) and 55 (preceding journal entries) | `PeriodDetails::$endDate` is null for those |
| `Name` is a `string` | An empty `<Name/>` for all periods of 49 of the 61 years, mostly before 2013 | `PeriodDetails::$name` is null then; a missing `<Name>` still throws |
| – | Periods are sorted by number | `list()` keeps the order |
| – | An unknown year gives fault `YearNotFound` with the message in the language of the user (`Year 1901 bestaat niet.`) | `CommandQueryFaultException`; `get()` throws `PeriodNotFoundException` |

### Not yet verified

- The commands other than `ChangeName`: whether `ChangeEndDate` also moves the start of the next period, and which numbers of periods `CreateYear` and `ResetYears` accept.
- The message when a transaction is saved in a closed period.

## Finder

The [Finder](/guides/finder/) was checked on 10 November 2026, read-only: a search of every documented type (`maxRows` 3) on a test company, `company=*` for 15 types, and the documented search fields and options with the errors they give. Documentation: [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) and the WSDL (`finder.asmx?wsdl`, in `wsdl/accounting/`).

### Requests

| Documentation | Live responses | SDK |
|---|---|---|
| "It is not possible to add the company code to the finder": use SelectCompany or the `office` option | The `CompanyCode` in the SOAP header works, as in the request example on the same page. Without header, the option `company` works too | The company of the client goes in the header; `company()` sends the option |
| `company` and `office` should not be provided together | Refused with `OptionCompanyAndOptionOfficeCannotBeBothProvided` | `FinderQuery::option()` throws an `InvalidArgumentException` before sending |
| `company=*` for `BNK`, `CUR`, `DIM`, `PAY`, `TRS`, `VAT`, `VATN`, `VGM`; the columns are not documented | Every row gets two extra columns at the end: company code and company ID (a GUID), also after the extra columns of `includehidden` and `modifiedsince`. `DMT` supports it too. `ASM`, `BDS`, `CTR`, `GRP`, `INV`, `TRT` ignore it: only code and name, no company per row | `inAllCompanies()` for the types that support it, the company per row from the extra columns; `companyReference()` throws for `*` |
| Search field 3 on `DIM`: bank account, or customer code for projects and activities | For projects and activities the third column is also `Bankrekening` (bank account) and pattern `*` gives no rows; searching on the customer code was not seen. Field 3 with `company=*` gives a server error | No method for the customer code; `inAllCompanies()` with `inBankAccount()` throws |
| Search fields 0 to 3 | Field 4 (address) works on `DIM`; a higher value gives `ParameterOutOfRange` with parameters `field`, `0`, `4` | `inAddress()` |
| `CQT` (cheques) with options `dim1`–`dim6`, `value`, `openvalue` | Without options a server error; `dim1` is refused with `OptionNotAllowed` | Not in `FinderType` |
| `SAR`: option `article` is mandatory | Without it: `OptionMandatory` with parameter `article` | `FinderType::SubItems`; the error is a `FinderException` |

### Responses

| Documentation | Live responses | SDK |
|---|---|---|
| `Search` returns `Message[]`; the response example has an empty `<SearchResult />` | Errors are in `<SearchResult>` as `<MessageOfErrorCodes>` with `Type`, `Text` (language of the user), `Code` and `Parameters`; there is then no `<data>` | `FinderException` with `FinderMessage`s; the codes are the `ErrorCodes` of the WSDL (`FinderErrorCode`). Before, this gave "Finder response has no &lt;data&gt; element." |
| Return fields "code, name" for most types | Column names in the language of the user (`Code`, `Naam`); `ICTCOUNTRY` has `Code`, `Name` | Values are read by position |
| `OFF`: code, name | Also country, VAT period and first quarter; `includeid` adds the ID after the name | `OfficeSummary` reads by position |
| `PRD`: title, name | Column names `title` and `name`; the name is often empty | – |
| `VGM`: code, name | Country, code, name | – |
| `VATN`: code, name, country, VAT number, relation company, city | As documented | – |
| `BSN`: number, description | Column names `Code`, `Naam` (no rows in the test companies) | – |

### Not yet verified

- Types without rows in the test companies: `BSN`, `DVT`, `GRP`, `GWY`, `ICTCOUNTRY`, `OIC`, `PIS`, `SPM`. `HND` is verified with the [hierarchies](#hierarchies). Twinfield accepts the search and gives the columns, but the values were not seen.
- Messages of type `Warning` or `Informational`: never seen. The SDK only throws on `Error`.
- Search field 3 on projects that have a customer: the test company has three projects, none of them found on field 3.

## Other resources

The differences for the other resources are in the [master data guide](/guides/master-data/), each in the section of the resource:

| Resource | Differences in short | Based on |
|---|---|---|
| [Customers](/guides/master-data/#customers) (`DEB`) | Empty lists left out; posting rules can be empty; an address can have no name; `<remittanceadvice>` is not documented as a root element | Live customers of the test companies |
| [Fixed assets](/guides/master-data/#fixed-assets-classic) (`AST`) | Status in lower case; `@locked` on every element; empty method, periods and dates | One test company, assets "to be activated" only |
| [General ledger accounts](/guides/master-data/#general-ledger-accounts) (`BAS`, `PNL`) | Empty child validations and groups left out; VAT code without attributes; hidden accounts | About 230 accounts on two test companies |
| [Rates](/guides/master-data/#rates) | Open end date 2999-12-31; prices with four decimals; `unit` not as documented; Finder type `TRT` | 459 live time rates |
| [Offices](/guides/master-data/#offices) | The responses are not documented; booleans `True`/`False`; undocumented timeframe values | 247 live offices |
| [Users](/guides/master-data/#users) | Needs a company; `restricted` is in use; lower case `templatecompanyaccess`; `acceptExtraCost` in camel case; undocumented `wkemailaddress` | All 18 users of a live organisation |
| [VAT codes](/guides/master-data/#vat-codes) | No `office` in the read response; undocumented `discountaccount` and `writeoffaccount`; rates with nine decimals and negative shares; delete documented with `dimensiongroup` | 491 VAT codes of 12 live companies |

## REST API

Checked live (November 2026) with the scopes of the REST API on four test companies, against the Swagger definition `open-v1` (in [`openapi/`](https://github.com/solvebeam/twinfield-php/tree/main/openapi)). The models of the definition match the live responses field for field; the differences are in the envelopes, the parameters and the documentation around them.

| Swagger definition | Live | SDK |
|---|---|---|
| No `securityDefinitions`; the scopes are only in the description of each operation | HTTP 403 without the scope of the operation, also with all identity scopes | `Scope` enum, and a hint about the scope in the `AccessDeniedException` |
| `{companyId}` without explanation; Twinfield support first said it is the company code | The GUID of the company, only available with the Finder `OFF` and option `includeid` | `companyId()` looks it up once; `company($code, $guid)` takes it |
| `host: accounting.twinfield.com` | The cluster URL of the token plus `/Api`, e.g. `https://api.accounting.twinfield.com/Api` | `clusterUrl()` of the token |
| Lists as plain arrays (articles), `PagedList` with `pageIndex` and `page` (accounts, spreading templates) or the model itself (fixed assets, VAT codes) | Always `{"items": […], "total": …, "filteredTotal": …}`; only the article cultures are a plain array | `RestPage` |
| Matching audit trail `{events, nextTimestamp}` | `{items}`, no `nextTimestamp`; `since` is inclusive | Pages by the last `logTimestamp` and skips repeated events |
| `limit` without maximum | 250 for VAT codes, 200 for the audit trail, 1000 for posting rules, changes and classes, 10000 for budgets and fixed assets | `all()` pages with 250 |
| Errors without body schema | A JSON string, or an object with a message per parameter | In the message of `RestException` |
| `matchDate` on every audit trail event | Not on `Unmatched` events | Nullable for `Unmatched` only |
| `createdAt` and `lastModifiedAt` as `date-time` | With `Z` for one VAT code, without `Z` in the list of VAT codes (same values) | Read as UTC |
| Posting rule lines with a general ledger account | 2 of 258 lines in a test company have none, only a VAT code | Nullable `generalLedgerAccountId` |
| `GET /sales/customers/changes` | HTTP 500 "An error occurred on the server" in 2 of 3 test companies, with any parameters; suppliers work | Thrown as `RestException` |
| `changes()` returns relations modified since a moment | Also returns relations whose `modified` is older than the moment | Check `modified` yourself when it matters |
| Fixed asset fields | Without `fields=*` only `id`, `code`, `description` and `version` (the "default fields" of the Swagger UI); the class list has no `code`, even with `fields=*` | Asks for all fields; `FixedAssetClassSummary` without code |
| Budget line paths with `{costCenterId}/{projectId}` | Leave them out for a line without cost center or project; the empty GUID gives 404 | `BudgetLineKey::toPath()` |
| `POST /fixedassets/backgroundreports/reconciliation` with `readFixedAssets` | HTTP 403 for the test user, which has the claim `fcReadFixedAssetsReports` | Not verified; see TODO.md |

The GUIDs of the REST API are the `uid` of the dimensions in the XML web services: checked for general ledger accounts and suppliers.

