# List rates

Lists the code and name of all rates of the company, or of the rates that match a pattern. Rates are the prices of Twinfield Time & Expenses: what an hour or a kilometre costs (internal rate) and what you invoice for it (external rate).

`search()` uses the Finder type `TRT` and gives `RateSummary` results with `code` and `name`. `all()` fetches page after page while you iterate, so it also works for hundreds of rates. Use `get()` for the type, unit, currency and prices (see [Read rate](../read-rate/)).

## Code

```php
$twinfield = Example::twinfield();

foreach ($twinfield->rates()->search($pattern)->all() as $rate) {
    echo $rate->code, '  ', $rate->name, PHP_EOL;
}
```

## Run

```sh
php examples/rates/list-rates/example.php
php examples/rates/list-rates/example.php 'T*'
```

```text
CONSULT  Consultancy
KM  Kilometres
T01  Bookkeeping 0 - 50
```
