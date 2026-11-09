# TODO

## Bank transactions: verify reading and writing live

Bank transactions (`transaction`, day book `BNK`) are tested offline with fixtures that follow the
documentation (`bank-transaction-*.xml`, fictitious values). Live, only the not found responses are
tested: no existing transaction is read yet. Creating, updating, `saveMany()`, posting and deleting
are not yet tested live: the write test needs an interactive confirmation.

```sh
composer test:write -- --company=1002_3 --filter=BankTransaction
```

To check:

- Read a few existing bank transactions (one imported by Twinfield, one entered by hand) and record
  one (`TWINFIELD_RECORD=1`, then `composer twinfield:anonymize`) to replace
  `bank-transaction-read.xml`. Are the extra elements of the documented response (`customersupplier`,
  `openvalue`, `matchlevel`, `@inuse`, `@vatobligatory`) really there, and does `<matches>` look as
  documented (`set/@status`, `method` always `payment`)?
- The shape of the live save, save many, post and delete responses, to replace the other fixtures.
- Does an update with `$transaction->edit()` change nothing? `edit()` leaves out the base and
  reporting amounts and the match sets: does Twinfield keep the matches of a line, or undo them?
- After deleting, `testLifecycle()` looks the transaction up in `deletedTransactions()->list()`. Does
  it show up right away, with the reason, the user code of the access token (same case?) and the
  transaction date? The list itself is verified live (read-only); only this round trip is not.

### Matching

`DetailLineInput::match()` sends a `<matches><set>` with a detail line (`MatchSetInput`). It follows
the request structure of the documentation, but the documentation has no request example with
matches and does not say what is required. Only tested offline.

- Does Twinfield accept a match set in a create request? And in an update of a provisional transaction?
- Which lines go in the set: only the lines of the other transactions (as the SDK does now, like the
  documented response), or also the line itself?
- Is `matchdate` required, and is the set `matchvalue` the sum of the lines (the SDK calculates it)?
- The sign of `matchvalue`: always positive, or negative for a credit note or a payment to a supplier?
  The SDK only refuses zero.
- Does matching a provisional transaction give a `temporary` set, and does posting make it `final`?
- What error does Twinfield give for a wrong line ID or a match value above the open amount? Add it
  to the write test, and to the [Matching](docs/src/content/docs/guides/transactions.md) section.

Then add matching to the write test (it needs an open sales invoice) and run the write examples in
`examples/bank-transactions/{create,update,delete,post}-bank-transaction` and
`bulk-import-with-errors`, and replace the illustrative output in their READMEs with the real output.

## Bank transactions: not yet implemented

- Total line fields `invoicenumber`, `freechar`, `comment`, `repvalue`, `reprate` and detail line
  fields `vatbasevalue`, `vatrepvalue` can't be sent yet. Probably rarely needed.

## Browse data: the other browse codes

Browse data (`browse()`, `browseDefinitions()`) is verified live with 030_3, 040_1 and 130_3 (see
the browse data section of the documentation differences). The definitions of all 38 browse codes
are read with the SDK (`browse-definitions/`), but queries only on these three.

- Add an example per browse code (`examples/browse-data/`), in batches: 000, 010, 100, 200; then
  020, 050_1, 060, 130_3, 230_2, 164, 400, 410, 680_2; then the rest. Run each one live: a value
  type other than `String`, `Number`, `Decimal`, `Value`, `Date` and `Datetime` throws, and must be
  added to `BrowseValueType`.
- Generate a reference page per browse code in the docs from `browse-definitions/` (fields,
  criteria, data types and values from `browsefields.xml`).
- Which browse codes sort? 000 does, 030_3 ignores the sort fields.

## Cash transactions: add live tests and verify live

Cash transactions (`transaction`, day book `KAS`) share their code with bank transactions
(`StatementTransaction`, `StatementTransactionInput`, `TransactionClient`), so the bank items above
apply to them too: matching and the fields that can't be sent yet. They are only tested
offline, with `cash-transaction-read.xml` (fictitious) and the bank fixtures for save, post and
delete. Nothing is tested live yet.

- Add an integration test (`tests/Integration/CashTransaction`) and a write test
  (`tests/Write/CashTransaction`), like the bank transaction tests, then run them:

  ```sh
  composer test:live -- --filter=CashTransaction
  composer test:write -- --company=1002_3 --filter=CashTransaction
  ```

- Read an existing cash transaction and record it (`TWINFIELD_RECORD=1`, then
  `composer twinfield:anonymize`) to replace `cash-transaction-read.xml`. Is the response the same as
  for bank transactions, or does it differ (e.g. no `<matches>`, other elements in the header)?
- Does `CashTransactionInput::forCashBook()` work with a live cash book, and does Twinfield accept
  `statement()` (number, opening and closing balance) for a cash book?
- Add write examples like the bank ones (`create`, `update`, `post`, `delete`), and run
  `examples/cash-transactions/daily-cash-register` and replace the illustrative output in its README
  with the real output.

## Journal transactions: verify writing live

Journal transactions (`transaction`, day books `MEMO`, `BEGINBALANS`, `SALARIS`, …) are tested
offline with fixtures that follow the live read responses (`journal-transaction-*.xml`, fictitious
values) and read-only live: 33 transactions of companies 1002 and 1002_3 parse and `edit()` gives
an input that balances; 50 deleted ones give a `TransactionNotFoundException`. Creating, updating,
`saveMany()`, posting and deleting are not yet tested live: the write test needs an interactive
confirmation.

```sh
composer test:write -- --company=1002_3 --filter=JournalTransaction
```

To check:

- The shape of the live save, save many, post and delete responses, to replace the fixtures. Record
  one (`TWINFIELD_RECORD=1`, then `composer twinfield:anonymize`).
- VAT: does Twinfield accept a detail line with `vatcode` and `vatvalue`, and book the VAT line on
  the same side (as the SDK's balance check assumes)? What does it do with a VAT code without
  `vatvalue`: calculate the VAT and refuse the unbalanced transaction, or something else? Is an
  update with `edit()` accepted with the VAT lines of the response?
- Does `regime` change anything in a company with one regime? Is `Commercial` or `Economic`
  refused there?
- Opening balance and year end: is a booking in `BEGINBALANS` outside period 0, or in `VJP`
  outside period 55, refused? With which message?
- Matching: `DetailLineInput::match()` on a journal line, e.g. to write off a payment difference
  against an open invoice (see the bank transaction items above).

Then run the write examples in `examples/journal-transactions/{create-journal-transaction,opening-balance}`
with `--save`.

## VAT codes: verify writing live

VAT codes (`vat`, Finder `VAT`) are tested offline with fixtures recorded live (`vat-read*.xml`,
anonymized) and read-only live: 491 VAT codes of 12 companies parse, and `edit()` builds for all
of them. `vat-save.xml`, `vat-save-error.xml` and the delete and activate responses are
fictitious. Creating, updating and deleting are not yet tested live: the write test needs an
interactive confirmation.

```sh
composer test:write -- --company=1002_3 --filter=VatCode
```

To check:

- Does an update with `$vatCode->edit()` change nothing (the write test compares the
  percentages)? Does Twinfield match percentages by date, and keep percentages that are left out
  of the request, or remove them? The SDK has no way to remove a percentage yet, because the
  documentation does not say how.
- Does Twinfield accept the account lines without `@id`? Does it keep or renumber them?
- `discountaccount` and `writeoffaccount` are returned (always empty) but not documented, and the
  SDK does not send them. Does an update clear them when they are set? Find out what they are for.
- Which fields are required when creating: is `shortname` required? Is a percentage without
  `name` refused (the SDK sends the name of the VAT code then)?
- Is `linetype` `detail` accepted, and what does its share mean? Only `vat` was seen live.
- Is the account share checked (100 in total, or 0 for reverse charge)? The SDK only checks
  -100 to 100 per line.
- The delete response: `@status` `deleted` or `hide` (as for the other master data)? `delete()`
  depends on it. And does `activate()` work with `status="active"`?
- The shape of the live save response, to replace `vat-save.xml`.

Then run the write examples in `examples/vat-codes/{create-vat-code,update-vat-percentage,delete-vat-code}`
and replace the illustrative output in their READMEs with the real output.

## Users: verify writing live

Users (Finder `USR`, XML `user`) are read-only tested live: all 18 users of the organisation parse,
`current()` matches `@iscurrentuser`, and `edit()` builds for every user. The fixtures follow the
live structure with fictitious values. Creating, updating and deleting are not yet tested live.

```sh
composer test:write -- --company=1002_3 --filter=User
```

The write test only updates the user of the access token and restores it: creating a user can cost
money (Twinfield charges per user). Create and delete a user by hand with
`examples/users/{create,delete}-user` on a test organisation, if possible.

To check:

- Does an update with `$user->edit()` change nothing? Does Twinfield clear elements that are left
  out (the write test compares `edit()` before and after)?
- Does Twinfield accept `acceptextracost` (documented) or only `acceptExtraCost` (as returned)?
- Does it accept the lower case `templatecompanyaccess` that it returns (`useandmanage`), or only the
  documented `UseAndManage`?
- Does sending `<offices>` set `restricted` to true, and how do you give a restricted user access to
  all companies again? The SDK never sends a list for users with access to all companies.
- What is the save response and the delete response? `save()` reads the user back, so it does not
  depend on it; `delete()` reads `@status` (`deleted` or `hide`).
- Do `invite()`, `password()`, `disable()` and `deletereason` work as documented?
- What do `support` (`none`, `primary`) and `@level` (`Supervisor`) mean? Not read yet.

Then run the write examples and replace the illustrative output in their READMEs with the real output.

## Suppliers: verify writing live

The suppliers (`CRD`) are tested offline with fixtures and read-only live: all 184 suppliers of
companies 1002 and 1002_3 parse, and `edit()` round-trips them. `supplier-read.xml` and
`supplier-read-posting-rules.xml` are recorded live (anonymized); `supplier-read-full.xml` and
`supplier-save.xml` are fictitious, based on the documentation. Creating, updating and deleting are
not yet tested live: the write test needs an interactive confirmation.

```sh
composer test:write -- --company=1002_3 --filter=Supplier
```

To check:

- Does Twinfield accept `cheques()` (`meansofpayment` cheques) without a pay code? No live supplier
  is paid by Twinfield (all have `none`), so `paymentfile` and `cheques` are only tested offline.
- Do `relationsreference` and `<remittanceadvice>` with `ByEmail` stick?
- `wayofpayment` and `payaddress` (BTL91) are documented, but never seen live. Does Twinfield accept
  and return them?
- What the bank attribute `destination` (always `0`) means, and whether `blocked` can be written.
- Can a posting rule line without ratio be sent (`PostingRuleLine::create('4500', null)`)?
- The shape of the live save response: record one (`TWINFIELD_RECORD=1`, then
  `composer twinfield:anonymize`) and replace `supplier-save.xml`.
- Record a supplier with groups and payment conditions and replace `supplier-read-full.xml`.

Then run the write examples in `examples/suppliers/{create,update,delete}-supplier` and replace the
illustrative output in their READMEs with the real output.

## Rates: verify writing live

Rates (`projectrate`, Finder `TRT`) are tested offline with fixtures and read-only live on company
1002 (459 time rates; company 1002_3 has none). Creating, updating and deleting are not yet tested
live: the write test needs an interactive confirmation.

```sh
composer test:write -- --company=1002_3 --filter=Rate
```

To check:

- Is `currency` required when creating a rate, or does Twinfield take the base currency? The SDK
  leaves it out when it is not set.
- Does an update with the line `id` change the existing line, and does a line without `id` add one?
  The write test expects two lines after `newPriceFrom()`.
- Does `<ratechange id="…" status="deleted">` remove a line (`removePrice()`)? The documentation
  does not say how to remove a line.
- What does `unit` mean? The documentation says `1` for a price per hour, but live time rates have
  units such as 93, 120 and 882 (minutes?). Update the docblocks of `Rate::$unit` and
  `RateInput::unit()` once known.
- The shape of the live save response. `save()` reads the rate back, so this does not block anything,
  but update `rate-save.xml` with a recorded response.
- Delete gives `Deleted` (or `Hidden` when in use) and activate gives `active`.

Then run the write examples in `examples/rates/{create-rate,update-rate-price,delete-rate}` and
replace the illustrative output in their READMEs with the real output. Also replace the output of
the read examples with real (anonymized) output, and add a quantity rate fixture recorded from a
company that has one (`rate-read-quantity.xml` is fictitious).

## Fixed assets: verify creating and updating live

The fixed assets (`AST`) are tested offline with fixtures, and read-only live on company 1002_3.
Creating, updating and deleting are not yet tested live: the write test needs an interactive
confirmation.

```sh
composer test:write -- --company=1002_3 --filter=FixedAsset
```

To check:

- Which fields Twinfield requires when creating an asset: the asset method? The cost center? Update
  the docblocks of `FixedAssetInput`, the create example and the guide to match.
- What an empty `nrofperiods` (`periods(null)`) becomes: is it inherited from the asset method
  (`inherited="true"`)? The write test now accepts both.
- The shape of the live save response. `FixedAsset::fromXml()` follows the documentation, where the
  echoed request elements have no `locked` attribute and `modified` is missing. If live responses
  do have them, the fallbacks in `fromXml()` (and the `fixed-asset-save.xml` fixture) can be made
  strict.
- What Twinfield does when a locked setting is sent anyway (e.g. the begin period of an active
  asset), to confirm the `LogicException` guard in `FixedAssetInput`.
- Delete gives `Deleted` (or `Hidden` when in use) and activate gives `active`.

## Fixed assets: active and sold assets only tested with fixtures

Company 1002_3 only has assets with status "to be activated". The variants for active and sold
assets (locked begin period, last depreciation and transaction lines, sell date) are based on the
structure seen in other administrations, but only tested with fictitious fixtures. Test them live
once a test company has an active or sold asset.

## Fixed assets: run the write examples

The output in the READMEs of `examples/fixed-assets/{create,update,delete}-fixed-asset` is
illustrative. Run the examples on company 1002_3 and replace it with the real output (with
fictitious names and codes where needed).

## General ledger accounts: verify writing live

The general ledger accounts (`BAS`, `PNL`) are tested offline with fixtures and read-only live on
companies 1002 and 1002_3 (about 230 accounts read). Creating, updating and deleting are not yet
tested live: the write test needs an interactive confirmation.

```sh
composer test:write -- --company=1002_3 --filter=GeneralLedgerAccount
```

To check:

- `financials/accounttype` is not sent (it is documented read-only, but the request example has
  it). Does Twinfield accept a new account without it?
- Does an empty `<childvalidations/>` remove the child validations, and `<groups/>` the groups?
- An account with a VAT code: are `name`, `shortname` and `type` on `<vatcode>` as documented?
  `VatCodeReference::fromXml()` throws when they are missing. Does `fixed="true"` stick?
- An account in a dimension group: does `<groups>` look as documented? No account in the test
  companies is in a group.
- The default `subanalyse` and `matchtype` of a new account (the update example prints them).
- Does Twinfield give the next free code when the code is left out? The SDK requires a code now.

Then run the write examples in `examples/general-ledger-accounts/{create,update,delete}-*` and
replace the illustrative output in their READMEs with the real output.

## General ledger accounts: rerun the live read tests

The live read tests passed once (9/9), but a rerun hit the Twinfield rate limit (HTTP 429) after a
lot of probing. Run them again on a quiet moment:

```sh
composer test:live -- --filter='GeneralLedgerAccount|general-ledger'
```

## General ledger accounts: fixtures with VAT code and groups

`general-ledger-account-read-full.xml` (VAT code, groups, periods, matchable) and
`general-ledger-account-save.xml` are fictitious, based on the documentation. Once the write test
has created an account with a VAT code and a group, record its read response
(`TWINFIELD_RECORD=1`, then `composer twinfield:anonymize`) and replace these fixtures with it.
Update the "Differences with the documentation" in the master data guide if anything differs.

## Offices: undocumented values

The office read model is based on 247 live offices of one accountancy environment; the Twinfield
documentation shows no responses. To check when other environments are available:

- The meaning of `vatmanagement/decltimeframe` and `icptimeframe` (`0`, `1`, `2`) and of
  `startingquarter` (`21`, `30`). Once known, they can become enums.
- Other values of `office/@status` (only `active` seen), `general/type` (only `accountantinternal`
  and `accountantexternal`), `general/paymentdiscount` (only `vatexclusive`), `general/scheme`
  (only `invoice`), `vatmanagement/accountingscheme` (only `invoicebased`) and
  `intercompany/@status` (only `none` and `both`; `in` and `out` are guesses).
- What `vatmanagement/seb`, `systemdimensions/teqcostcenter` and `forbalynd`,
  `general/allowicmt940` and `lockwordinv` are.
- Indirect intercompany relations (`indirect` is `false` everywhere).

## Projects: verify live

The projects (`PRJ`) are tested offline with fixtures based on the documentation and on the live
activity responses (the XML is the same), and read-only live (`composer test:live -- --filter=Project`
passes; the code format is `P[0-9][0-9][0-9][0-9]`, financial level 3). Creating, updating and
deleting are not yet tested live: the write test needs an interactive confirmation.

```sh
composer test:write -- --company=1002_3 --filter=Project
```

To check:

- Does Twinfield accept a name of more than 40 characters (documented: 80)? The write test
  renames the project to a name of 55 characters.
- Does Twinfield refuse a project without a customer, or one with an inherited customer? The
  documentation says "a project always needs to be linked to a customer".
- The shape of the save response (`project-save.xml` follows the documentation, without
  `modified`). Live reads parse fine; record one with all settings filled
  (`TWINFIELD_RECORD=1`, then `composer twinfield:anonymize`) and replace `project-read.xml`.

Then run the write examples in `examples/projects/{create,update,delete}-project` and replace the
illustrative output in their READMEs (and in `list-projects`, `search-projects` and
`read-project`) with the real output.

## Sales transactions: verify writing live

Sales transactions (`transaction`, day book `VRK`) are read live: 7 transactions of company 1002
parse and give an input with `edit()`, and Finder `IVT` works (see
[Differences with the documentation](docs/src/content/docs/guides/documentation-differences.md#sales-transactions)).
Writing is only tested offline: the save, save many, post, delete and change pay status fixtures
follow the live read responses and the documentation, with fictitious values.

```sh
composer test:write -- --company=1002_3 --filter=SalesTransaction
```

To check:

- The shape of the save response. The documented response example has no `matchstatus` on the VAT
  line; the live read responses have it. If the save response leaves it out, `create()` throws after
  Twinfield saved the transaction: then make `VatLine::$matchStatus` nullable for that case.
- Does a total line without `dim1` work (Twinfield takes the account of the customer)?
- Does an update with `edit()` work? It sends the VAT lines of the response back (with `baseline`
  1, as Twinfield returns it).
- Is `duedate` required on create, and what does Twinfield set without it?
- Does `changepaystatus` work on a provisional transaction, or only on a final one? What does the
  response look like?
- Record a paid invoice (`TWINFIELD_RECORD=1`, then `composer twinfield:anonymize`) to check the
  `<matches>` of the total line; `sales-transaction-read-credit-note.xml` is fictitious.

Not yet implemented: the exchange rate and base and reporting amounts of the total line (`rate`,
`basevalue`, `repvalue`, `reprate`) can't be sent yet.

Then run the examples in `examples/sales-transactions/{create-sales-transaction,credit-note}` with
`--save`, and replace the dry-run output in their READMEs with the real output.

## Purchase transactions: verify writing live

Purchase transactions (`transaction`, day book `INK`) are read live: 25 transactions of companies
1002 and 1002_3 parse and give an input with `edit()` with the same total, and Finder `IVT` with
`supplier()` works (see
[Differences with the documentation](docs/src/content/docs/guides/documentation-differences.md#purchase-transactions)).
They share their input code with sales transactions (`AbstractInvoiceTransactionInput`). Writing
is only tested offline: the fixtures (`purchase-transaction-*.xml`) follow the live read responses
and the documentation, with fictitious values. Recording with `TWINFIELD_RECORD=1` and
`composer twinfield:anonymize` did not give usable fixtures: the anonymizer also replaces fixed
values such as `debitcredit`, `matchstatus` and `@location`.

```sh
composer test:write -- --company=1002_3 --filter=PurchaseTransaction
```

To check:

- The shape of the save, post, delete and change pay status responses, to replace the fixtures.
- Does an update with `edit()` work for an imported invoice with negative discount lines (`debit`
  `-11.72`)? `edit()` sends them back as Twinfield returned them.
- Is `duedate` required on create? One purchase invoice entered by hand has none.
- Does `changepaystatus` work on a provisional transaction? Is it the same setting as `freechar` `N`
  on the total line (`excludeFromPaymentRuns()` on the input)?
- Keep the fixed values in `XmlAnonymizer`, so a recorded transaction can replace the fixtures.

Then run the examples in `examples/purchase-transactions/{create-purchase-transaction,credit-note}`
with `--save`, and replace the dry-run output in their READMEs with the real output.

## Sales invoices: verify writing live

Sales invoices (`salesinvoice`, Classic invoicing module) are read live: 40 invoices of type
`FACTUUR` of one test company (2010–2011, concept and final), each read with the SDK and turned into
an input with `edit()`, plus Finder `INV` and the not found response (`composer test:live --
--filter=SalesInvoice` with `TWINFIELD_SALES_INVOICES=FACTUUR:1,FACTUUR:5`). Writing is only tested
offline: the save, calculate and error fixtures follow the documented response, with fictitious
values.

```sh
composer test:write -- --company=1002 --filter=SalesInvoice
```

The write test leaves a concept invoice behind: the API can't delete sales invoices. Set
`TWINFIELD_WRITE_FINAL_INVOICE=1` to also make it final; that books a sales transaction that can't
be deleted either.

To check:

- The shape of the save response: does it have all elements of the read response (`SalesInvoice`
  requires them), with the invoice number? Record it to replace `sales-invoice-save.xml`.
- `calculateonly="true"`: does Twinfield really save nothing, and what does the response look like
  (no invoice number)? Replace `sales-invoice-calculate.xml`. If it saves nothing, the write guard
  could allow it, so `calculate()` can be tested with `composer test:live`.
- The one-off item `0`: what are `subarticle` and `description` in the response?
  `SalesInvoiceLine::$subarticle` is required, as in all live lines so far.
- Prices: is a `unitspriceexcl` ignored or refused when the price of the article can't be changed?
  The documentation example sends `83.94` for 2 units and gets `41.97` back. `edit()` sends the
  prices back: does an update with an unchanged price work?
- Update: are lines left out of the request removed (`removeLine()`), or kept?
- Are `invoicedate`, `duedate`, `bank` and `paymentmethod` really optional on create? What does
  Twinfield set without them?
- Credit invoice: a negative `quantity`, or an invoice type of its own?
- An invoice type with prices including VAT (`unitspriceinc`): none in the test companies.
- Performance type and date on lines and header (ICT): never seen live.
- Is the sub-article in the documentation (`501.01`) a valid code? The SDK only checks the length
  of article codes.

Then run `examples/sales-invoices/create-sales-invoice` with `--calculate` and `--save`, and
`finalize-sales-invoice` with `--save`, and replace the illustrative output in their READMEs with
the real output.

Not yet implemented: articles (items) themselves (`<article>`, Finder `ART`). The old manual (`manuals/Webservices-Manual-Sales-Invoices.pdf`) is not read yet: it needs
`pdftotext` (`brew install poppler`).

## Reversal transactions: verify writing live

`reversalTransactions()` reads live (`getreversetransactioninfo`); `postFinal()`, `postProvisional()`
and `undo()` are tested offline with fixtures that follow the documentation
(`reversal-transaction-{post-final,post-provisional,undo}.xml`, fictitious values).

```sh
composer test:write -- --company=1002_3 --filter=ReversalTransaction
```

The write test only uses a provisional transaction (`postProvisional()`, `get()`, `undo()`, then
deletes it). `postFinal()` makes two final transactions that cannot be deleted: run
`examples/reversal-transactions/reverse-journal-transaction` once on a test company by hand.

To check:

- The real responses of the three actions; record them (`TWINFIELD_RECORD=1`, then
  `composer twinfield:anonymize`) to replace the fixtures, and replace the illustrative output in the
  README of the example.
- How `reverseinnextperiod` is set (not in any request); perhaps a setting of the day book.
- `postFinal()` on a provisional or already reversed transaction, `postProvisional()` on a final one,
  `undo()` without settings: which errors?
- Does posting a provisional transaction with reversal settings create the reversed transaction?
- `<reverse>` without `action`: is it really a read, as the documentation example suggests?

## Electronic bank statements: verify live

`electronicBankStatements()` (`<statements>` with `<statement target="electronicstatements">`) is only
tested offline, with fixtures based on the response example of the documentation
(`electronic-bank-statement-create*.xml`, fictitious values). Nothing is tested live yet.

```sh
composer test:write -- --company=1002_3 --filter=ElectronicBankStatement
```

The write test leaves a statement (0.01 in, 0.01 out) in the company: a statement can't be changed
or deleted through the API.

To check:

- The SDK always sends the `<statements importduplicate="…">` root, also for one statement; the
  request example of the documentation has only `<statement>`. Does the response root
  `<statements>` have `result`, and does each `<statement>` have its own `result` (needed by
  `createMany()`)? Record the response (`TWINFIELD_RECORD=1`, then `composer twinfield:anonymize`)
  to replace the fixtures.
- The shape of the response: are `account`/`iban`, `office` and `code` (with `@name`) always there,
  are `contraaccount`/`contraiban`, `endtoendid` and `returnreason` really left out (as in the
  example), and do the dimensions use `@dimensiontype`?
- Duplicates with `importduplicate="0"`: error, warning, or silently skipped? When is a statement a
  duplicate (same number, same lines)? The write test prints what Twinfield answers.
- Is a statement without lines accepted? The SDK refuses it now.
- A bank book without allocation rules, or with a clearing code and no `sortcode`: which errors?
- Run `examples/electronic-bank-statements/import-electronic-bank-statement` with `--save` once and
  replace the illustrative line in its README with the real output.
- Reading (`list()`, `listByCreationDate()`) is verified live on a company with downloaded
  statements, but not on a statement imported with `create()`: the write test now reads the
  imported statement back. Also check there whether `listByCreationDate()` finds it, and whether an
  imported statement is posted right away.
- Reading a statement that is not posted (`TransactionNumber` nil), and VAT lines, cheque and
  paying-in slip numbers: only unit tested with a fictitious response
  (`electronic-bank-statements-list-unposted.xml`).

## Transaction blocked value: verify live

`transactionBlockedValues()` (TransactionBlockedValueService) sends both commands in the WSDL order;
the fault fixture (`transaction-blocked-value-fault-cannot-be-registered.xml`) is fictitious. Nothing
is tested live yet.

```sh
composer test:write -- --company=1002_3 --filter=TransactionBlockedValue
```

To check:

- Does Twinfield accept a blocked value on a provisional transaction (the write test uses one), or
  only on a final one? On which lines: only the total line of a sales or purchase invoice, or also
  detail lines and journal lines (the documentation example uses `MEMO` line 1)?
- Zero, negative values (e.g. on a credit note) and values above the open amount: accepted, or which
  fault? Add a check to `register()` once it is clear.
- `unregister()` on a line without blocked value: no error, or `BlockedValueCannotBeUnregistered`?
- Registering twice: does the second value replace the first?
- Is the blocked value visible anywhere (transaction XML, Finder, browse data)? If so, read it.
- Record the real fault responses (`TWINFIELD_RECORD=1`, then `composer twinfield:anonymize`) to
  replace the fixture, and run the examples in `examples/transaction-blocked-values/` and replace
  the illustrative output in their READMEs with the real output.
