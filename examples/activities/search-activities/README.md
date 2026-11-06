# Search activities

Searches activities by name and shows the first page of results.

Every method returns a new search, so you can keep a base search and refine it:

| Method | What it does |
|---|---|
| `search('A*')` | Pattern: `*` matches anything, `?` one character. Default `*`. |
| `inCodeAndName()` / `inCode()` / `inName()` | Where the pattern must match. Default: code and name. |
| `modifiedSince($date)` | Only activities changed on or after this moment. |
| `group('DEV*')` | Only activities in a dimension group; wildcards are allowed. |
| `limit(10)` / `offset(20)` | Page size (default 100) and results to skip. |
| `get()` | One page: `totalRows` and the `ActivitySummary` items. |
| `all()` | All results over all pages, page by page while you iterate. |
| `count()` | Number of matches, with one small request. |

## Code

```php
$twinfield = Example::twinfield();

$pattern = $argv[1] ?? '*';

$page = $twinfield->activities()
    ->search($pattern)
    ->inName()
    ->modifiedSince(new DateTimeImmutable('2010-01-01'))
    ->limit(10)
    ->get();

echo $page->totalRows, ' activities with a name like "', $pattern, '", first ', count($page), ':', PHP_EOL;

foreach ($page as $activity) {
    echo $activity->code, '  ', $activity->name, PHP_EOL;
}
```

## Run

```sh
php examples/activities/search-activities/example.php '*BTW*'
```

```text
1 activities with a name like "*BTW*", first 1:
A002  Aangifte BTW
```
