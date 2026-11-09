# Search VAT codes

Searches VAT codes of one type, sales or purchases, by name. It shows the number of matches and the first ten.

`search()` returns an immutable search, so each method gives a new search:

| Method | What it does |
|---|---|
| `vatType(VatCodeType::Sales)` | Only VAT codes for sales; `VatCodeType::Purchase` for purchases (Finder option `vattype`). |
| `inName()`, `inCode()`, `inCodeAndName()` | Where the pattern matches; code and name by default. |
| `limit(10)`, `offset(20)` | One page of results with `get()`. |
| `count()` | The number of matches, with one small request. |
| `all()` | All results, page by page while you iterate. |

## Code

```php
$twinfield = Example::twinfield();

$search = $twinfield->vatCodes()->search($pattern)->inName()->vatType($type);

echo $search->count(), ' ', $type->value, ' VAT codes match ', $pattern, PHP_EOL;

foreach ($search->limit(10)->get() as $vatCode) {
    echo $vatCode->code, '  ', $vatCode->name, PHP_EOL;
}
```

## Run

```sh
php examples/vat-codes/search-vat-codes/example.php
php examples/vat-codes/search-vat-codes/example.php purchase '*Hoog*'
```

```text
4 purchase VAT codes match *
ICV-L  IC verwervingen binnnen EU
IH  I Hoog
IL  I Laag
IN  I Nul
```
