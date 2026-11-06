# List asset methods

Lists the code and name of all asset methods of the company, or of those matching a pattern.

An asset method says how fixed assets are depreciated (linear over a number of periods, or a percentage per year) and on which balance sheet and profit and loss accounts. `assetMethods()->search()` searches them with the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) (type `ASM`); `all()` goes through all pages for you, fetching the next page only when you get there.

| Method | What it does |
|---|---|
| `search('MACH*')` | Pattern: `*` matches anything, `?` one character. Default `*`. |
| `inCodeAndName()` / `inCode()` / `inName()` | Where the pattern must match. Default: code and name. |
| `limit(10)` / `offset(20)` | Page size (default 100) and results to skip. |
| `get()` / `all()` / `count()` | One page, all results, or only the number of matches. |

Each result is an `AssetMethodSummary` with `code` and `name`. Use `get()` for all settings (see [Read asset method](../read-asset-method/)).

## Code

```php
$twinfield = Example::twinfield();

// Optional search pattern from the command line, e.g. `MACH*`.
$pattern = $argv[1] ?? '*';

foreach ($twinfield->assetMethods()->search($pattern)->all() as $method) {
    echo $method->code, '  ', $method->name, PHP_EOL;
}
```

## Run

```sh
php examples/asset-methods/list-asset-methods/example.php
php examples/asset-methods/list-asset-methods/example.php 'MACH*'
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
CARS  Cars reducing balance
MACHINE  Machines 5 years linear
```
