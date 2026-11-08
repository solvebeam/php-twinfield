# Search projects

Searches projects by name and shows the first page of results.

Every method returns a new search, so you can keep a base search and refine it:

| Method | What it does |
|---|---|
| `search('P*')` | Pattern: `*` matches anything, `?` one character. Default `*`. |
| `inCodeAndName()` / `inCode()` / `inName()` | Where the pattern must match. Default: code and name. |
| `modifiedSince($date)` | Only projects changed on or after this moment. |
| `group('WEB*')` | Only projects in a dimension group; wildcards are allowed. |
| `limit(10)` / `offset(20)` | Page size (default 100) and results to skip. |
| `get()` | One page: `totalRows` and the `ProjectSummary` items. |
| `all()` | All results over all pages, page by page while you iterate. |
| `count()` | Number of matches, with one small request. |

## Code

```php
$twinfield = Example::twinfield();

$pattern = $argv[1] ?? '*';

$page = $twinfield->projects()
    ->search($pattern)
    ->inName()
    ->modifiedSince(new DateTimeImmutable('2010-01-01'))
    ->limit(10)
    ->get();

echo $page->totalRows, ' projects with a name like "', $pattern, '", first ', count($page), ':', PHP_EOL;

foreach ($page as $project) {
    echo $project->code, '  ', $project->name, PHP_EOL;
}
```

## Run

```sh
php examples/projects/search-projects/example.php '*website*'
```

```text
1 projects with a name like "*website*", first 1:
P0001  Website redesign
```
