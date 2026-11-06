# Paginate

Goes through all results of a search, page by page.

`get()` returns one page. `all()` returns a generator that fetches the next page only when your loop needs it, so even large results use little memory. `limit()` sets the page size.

## Code

```php
$twinfield = Example::twinfield();

$count = 0;

foreach ($twinfield->finder(FinderType::Countries)->limit(100)->all() as [$code, $name]) {
    if (++$count <= 5) {
        echo "{$code} {$name}", PHP_EOL;
    }
}

echo "… {$count} countries in total, fetched in pages of 100", PHP_EOL;
```

Twinfield limits the number of requests per minute. A very small page size means many requests; when the limit is hit, a `RateLimitExceededException` is thrown.

## Run

```sh
php examples/finder/paginate/example.php
```

```text
AD Andorra
AE United Arab Emirates
AF Afghanistan
AG Antigua and Barbuda
AI Anguilla
… 250 countries in total, fetched in pages of 100
```
