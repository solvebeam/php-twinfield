# List fixed assets

Lists the code and name of all fixed assets, or of those matching a pattern.

`search()` uses the Finder (type `DIM` with `dimtype` `AST`) and searches code and name. `inCode()` or `inName()` narrow that down, `group('VEHICLES')` gives only the assets in a dimension group, and `modifiedSince()` only those changed since a date. `all()` fetches the pages lazily; use `get()` for one page and `count()` for the number of matches.

The Finder only gives the code and name: use `get()` for all details of an asset, see [Read fixed asset](../read-fixed-asset/).

## Code

```php
$twinfield = Example::twinfield();

// A pattern from the command line, e.g. `6*`, or else all fixed assets.
$search = $twinfield->fixedAssets()->search($argv[1] ?? '*');

// Only the assets in one dimension group: $search = $search->group('VEHICLES');

echo $search->count(), ' fixed assets', PHP_EOL;

foreach ($search->all() as $asset) {
    echo $asset->code, '  ', $asset->name, PHP_EOL;
}
```

## Run

```sh
php examples/fixed-assets/list-fixed-assets/example.php
php examples/fixed-assets/list-fixed-assets/example.php '6001*'
```

```text
3 fixed assets
60000  Coffee machine
60010  Delivery van
60011  Electric van & charger
```
