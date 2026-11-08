# TODO

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
