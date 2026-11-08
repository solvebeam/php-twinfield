# Search offices

Searches offices (companies) by name, and counts how many of them are not in a tax group.

`offices()->search()` uses the Twinfield Finder and returns `OfficeSummary` objects, like `list()`. Every method returns a new search, so a base search can be reused:

- `search('10*')`: the pattern; `*` matches anything, `?` one character.
- `inCode()`, `inName()` or `inCodeAndName()` (default): where the pattern must match.
- `withoutTaxGroup()`: only offices that are not in a fiscal unity (tax group), are no template and have no VAT declaration in progress. These are the offices that can join a fiscal unity.
- `limit()` and `offset()` for paging, `get()` for one page, `all()` for all results and `count()` for the number of results.

## Code

```php
$twinfield = Example::twinfield();

$pattern = $argv[1] ?? '*B.V.*';

$search = $twinfield->offices()->search($pattern)->inName();

$page = $search->limit(5)->get();

echo $page->totalRows, ' offices with a name like "', $pattern, '", first ', count($page), ':', PHP_EOL;

foreach ($page as $office) {
    echo $office->code, '  ', $office->name, PHP_EOL;
}

// Offices that can join a fiscal unity: not in a tax group, no template, no VAT declaration in progress.
echo $search->withoutTaxGroup()->count(), ' of them are not in a tax group.', PHP_EOL;
```

## Run

```sh
php examples/offices/search-offices/example.php
php examples/offices/search-offices/example.php '*Holding*'
```

```text
12 offices with a name like "*B.V.*", first 5:
1000  Example Holding B.V.
1001  Example Retail B.V.
1002  Example Services B.V.
1005  Example Logistics B.V.
1008  Example Consultancy B.V.
9 of them are not in a tax group.
```
