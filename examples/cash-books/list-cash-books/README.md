# List cash books

Lists the code and name of all cash books of the company, or of those matching a pattern.

`cashBooks()->search()` searches with the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) type `BNK` and option `banktype=1`, so bank books are left out. `all()` goes through all pages for you. The search has the same methods as the activity search: `inCode()`, `inName()`, `modifiedSince()`, `limit()` and `offset()`.

Each result is a `CashBookSummary` with `code` and `name`. Use `get()` for the short name and ledger account (see [Read cash book](../read-cash-book/)).

## Code

```php
$twinfield = Example::twinfield();

foreach ($twinfield->cashBooks()->search($argv[1] ?? '*')->all() as $cashBook) {
    echo $cashBook->code, '  ', $cashBook->name, PHP_EOL;
}
```

## Run

```sh
php examples/cash-books/list-cash-books/example.php
php examples/cash-books/list-cash-books/example.php 'K*'
```

```text
KAS  Kas
VRK_1  Verkopen pin
```
