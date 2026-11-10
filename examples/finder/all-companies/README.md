# Search in all companies

Searches the VAT codes of all companies the user has access to, in one Finder search.

`inAllCompanies()` sends the Finder option `company=*`. Twinfield then adds two columns to every row: the company code and the company ID. The SDK reads the company from them, so every summary has its own `company`. It works for dimensions (customers, suppliers, general ledger accounts, …), VAT codes, bank books, cash books, currencies and dimension types; other searches throw an `InvalidArgumentException`.

Use `company('1001')` instead to search in one other company than the company of the client.

## Code

```php
$twinfield = Example::twinfield();

// One search over all companies the user has access to (Finder option company=*).
$page = $twinfield->vatCodes()->search()->inAllCompanies()->limit(10)->get();

echo "{$page->totalRows} VAT codes in all companies, first ", count($page), ':', PHP_EOL;

foreach ($page as $summary) {
    echo $summary->company->code, ' ', $summary->code, ' ', $summary->name, PHP_EOL;
}
```

## Run

```sh
php examples/finder/all-companies/example.php
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
1250 VAT codes in all companies, first 10:
1000 VH BTW hoog
1000 VL BTW laag
1001 VH BTW hoog
…
```
