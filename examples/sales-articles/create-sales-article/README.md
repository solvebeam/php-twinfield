# Create sales article

> **This example changes data in Twinfield.** It deletes the article again, but only run it on a test administration.

Creates a sales article, raises its gross price and deletes it again (REST API).

- `SalesArticleInput::create($name, $vatCodeId)` with the code, the net or gross price (Twinfield calculates the other) and the translations.
- `update($article->edit()->…)` sends the version the article had (`If-Match`): when someone changed it in the meantime, Twinfield refuses with a `VersionConflictException` (HTTP 412).
- A code that is already in use gives a `ValidationException` (HTTP 422).
- `delete($id, $version)` deletes it; without version it deletes whatever the version.

Needs the scopes `writeArticles` and `readVat`. Not verified live yet: see TODO.md.

## Code

```php
$twinfield = Example::twinfield();
$articles = $twinfield->salesArticles();

$vatCode = $twinfield->vatCodes()->searchDetails()->type(VatCodeType::Sales)->limit(1)->get()->items[0];

$article = $articles->create(
    SalesArticleInput::create('Example bike', $vatCode->id)
        ->code('EXAMPLE-' . random_int(1000, 9999))
        ->netPrice('825.00')
        ->translations(['en-GB' => 'Example bike', 'nl-NL' => 'Voorbeeldfiets']),
);

echo 'Created ', $article->code, ' ', $article->name, ': ', $article->netPrice, ' excl. VAT, ';
echo $article->grossPrice, ' incl. VAT ', $vatCode->code, PHP_EOL;

$article = $articles->update($article->edit()->grossPrice('999.00'));

echo 'Updated to ', $article->grossPrice, ' incl. VAT (version ', $article->version, ')', PHP_EOL;

$articles->delete($article->id, $article->version);

echo 'Deleted ', $article->code, PHP_EOL;
```

## Run

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/sales-articles/create-sales-article/example.php
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
Created EXAMPLE-4821 Example bike: 825.0000 excl. VAT, 998.2500 incl. VAT VH
Updated to 999.0000 incl. VAT (version 2)
Deleted EXAMPLE-4821
```
