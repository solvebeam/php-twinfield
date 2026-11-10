# List sales articles

Lists the sales articles with their prices and VAT code, from the REST API.

- `salesArticles()->search($pattern)` searches code and name; `inCulture()`, `scope()` and `vatCodes()` narrow it down.
- Prices have four decimals (`UnitPrice`); the gross price includes VAT. Twinfield allows an article without code.
- The article refers to its VAT code by GUID; `vatCodes()->searchDetails()` gives the codes.
- `translations` has the name per culture; `cultures()` lists the cultures.

Needs the scope `readArticles` (and `readVat` for the VAT codes).

## Code

```php
$twinfield = Example::twinfield();

$vatCodes = [];

foreach ($twinfield->vatCodes()->searchDetails()->all() as $vatCode) {
    $vatCodes[$vatCode->id] = $vatCode->code;
}

foreach ($twinfield->salesArticles()->search($argv[1] ?? '*')->orderBy('name')->all() as $article) {
    echo sprintf(
        '%s %s: %s excl. VAT, %s incl. VAT (VAT code %s)',
        $article->code ?? '(no code)',
        $article->name,
        $article->netPrice,
        $article->grossPrice,
        $vatCodes[$article->vatCodeId] ?? $article->vatCodeId,
    ), PHP_EOL;

    foreach ($article->translations as $culture => $name) {
        echo '  ', $culture, ': ', $name, PHP_EOL;
    }
}
```

## Run

```sh
php examples/sales-articles/list-sales-articles/example.php
php examples/sales-articles/list-sales-articles/example.php 'Bike*'
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
BIKE Bike: 825.0000 excl. VAT, 998.2500 incl. VAT (VAT code VH)
  en-GB: Bike
  nl-NL: Fiets
(no code) Repair per hour: 55.0000 excl. VAT, 66.5500 incl. VAT (VAT code VH)
```
