# List VAT codes

Lists the code and name of all VAT codes of the company, or of the VAT codes that match a pattern.

`search()` uses the Finder type `VAT` and gives `VatCodeSummary` results with `code` and `name`. `all()` fetches page after page while you iterate. Use `get()` for the type, the percentages and the accounts (see [Read VAT code](../read-vat-code/)).

## Code

```php
$twinfield = Example::twinfield();

foreach ($twinfield->vatCodes()->search($pattern)->all() as $vatCode) {
    echo $vatCode->code, '  ', $vatCode->name, PHP_EOL;
}
```

## Run

```sh
php examples/vat-codes/list-vat-codes/example.php
php examples/vat-codes/list-vat-codes/example.php 'V*'
```

```text
EXP_BEU  Export Buiten EU
ICP  Intra Communitaire Prestaties
ICV-H  IC verwervingen binnnen EU
ICV-L  IC verwervingen binnnen EU
IH  I Hoog
IL  I Laag
IN  I Nul
PRIVE-H  Prive gebruik 19%
PRIVE-L  Prive gebruik 9%
VH  BTW Hoog
VL  BTW 9%
VN  BTW 0%
```
