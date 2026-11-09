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

- Browse (searching transactions, e.g. by date, customer or invoice number): Browse Data API, not
  on the bank transactions page.
- The `BankStatementService`: bank statements imported by Twinfield itself (see the README).
- Total line fields `invoicenumber`, `freechar`, `comment`, `repvalue`, `reprate` and detail line
  fields `vatbasevalue`, `vatrepvalue` can't be sent yet. Probably rarely needed.

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
