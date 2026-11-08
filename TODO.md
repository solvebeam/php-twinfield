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
