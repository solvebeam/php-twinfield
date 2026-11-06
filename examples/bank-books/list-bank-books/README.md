# List bank books

Lists the code and name of all bank books of the company, or of those matching a pattern.

`bankBooks()->search()` searches with the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) type `BNK` and option `banktype=0`, so cash books are left out. `all()` goes through all pages for you. The search has the same methods as the activity search: `inCode()`, `inName()`, `modifiedSince()`, `limit()` and `offset()`.

Each result is a `BankBookSummary` with `code` and `name`. Use `get()` for the bank account and other settings (see [Read bank book](../read-bank-book/)).

## Code

```php
$twinfield = Example::twinfield();

foreach ($twinfield->bankBooks()->search($argv[1] ?? '*')->all() as $bankBook) {
    echo $bankBook->code, '  ', $bankBook->name, PHP_EOL;
}
```

## Run

```sh
php examples/bank-books/list-bank-books/example.php
php examples/bank-books/list-bank-books/example.php 'R*'
```

```text
BNK  Standaard bank
ING  ING 3255112
RABO  Rabobank 123456789
```
