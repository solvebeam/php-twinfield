# Search dimensions

Searches debtors with the Twinfield Finder.

`finder()` starts a query for a `FinderType`. Each method returns a new query, so you can build a base query once and reuse it:

- `pattern('13*')`: search pattern; `*` matches anything, `?` one character.
- `option('dimtype', 'DEB')`: Finder option, here: only debtors (`CRD` for creditors).
- `limit(10)` and `offset(20)`: page size and number of rows to skip.
- `get()`: runs the query and returns one page as a `FinderResult`.

A `FinderResult` has `totalRows` (over all pages), `columns` and `rows`. Each row is a list of strings in column order. Column names are in the language of the Twinfield user (e.g. `Naam`), so read values by position.

## Code

```php
$twinfield = Example::twinfield();

$debtors = $twinfield->finder(FinderType::Dimensions)
    ->pattern('*')
    ->option('dimtype', 'DEB')
    ->limit(10)
    ->get();

echo "{$debtors->totalRows} debtors, first ", count($debtors), ':', PHP_EOL;

foreach ($debtors as [$code, $name]) {
    echo "{$code} {$name}", PHP_EOL;
}
```

## Run

```sh
php examples/finder/search-dimensions/example.php
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
1532 debtors, first 10:
10000 Example Customer A
10001 Example Customer B
10002 Example Customer C
…
```
