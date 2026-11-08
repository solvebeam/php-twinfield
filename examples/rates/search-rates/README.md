# Search rates

Searches rates of one type, hours or quantities, by name. It shows the number of matches and the first ten.

`search()` returns an immutable search, so each method gives a new search:

| Method | What it does |
|---|---|
| `rateType(RateType::Time)` | Only rates for hours; `RateType::Quantity` for quantities such as kilometres (Finder option `ratetype`). |
| `inName()`, `inCode()`, `inCodeAndName()` | Where the pattern matches; code and name by default. |
| `limit(10)`, `offset(20)` | One page of results with `get()`. |
| `count()` | The number of matches, with one small request. |
| `all()` | All results, page by page while you iterate. |

## Code

```php
$twinfield = Example::twinfield();

$search = $twinfield->rates()->search($pattern)->inName()->rateType($type);

echo $search->count(), ' ', $type->value, ' rates match ', $pattern, PHP_EOL;

foreach ($search->limit(10)->get() as $rate) {
    echo $rate->code, '  ', $rate->name, PHP_EOL;
}
```

## Run

```sh
php examples/rates/search-rates/example.php
php examples/rates/search-rates/example.php quantity '*km*'
```

```text
2 time rates match *
CONSULT  Consultancy
T01  Bookkeeping 0 - 50
```
