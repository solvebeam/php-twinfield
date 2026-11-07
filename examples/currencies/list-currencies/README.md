# List currencies

Lists the code and name of all currencies of the company, or those that match a pattern.

`currencies()->search()` searches them with the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) (type `CUR`). `count()` asks for the number of matches with one small request; `all()` goes through all pages for you.

Each result is a `CurrencySummary` with `code` and `name`. Use `get()` for the short name and the exchange rates (see [Read currency](../read-currency/)).

The search is immutable and can be refined further, e.g. `search('U*')->inCode()`, `->inName()` or `->limit(10)->get()` for one page. To check one code, use `exists('USD')`.

## Code

```php
$twinfield = Example::twinfield();

// A pattern from the command line, e.g. `U*`, or else all currencies.
$search = $twinfield->currencies()->search($argv[1] ?? '*');

echo $search->count(), ' currencies', PHP_EOL;

foreach ($search->all() as $currency) {
    echo $currency->code, '  ', $currency->name, PHP_EOL;
}
```

## Run

```sh
php examples/currencies/list-currencies/example.php
```

```text
3 currencies
EUR  Euro
GBP  Pound sterling
USD  US dollar
```
