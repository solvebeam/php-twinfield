# Delete currency

> **This example changes data in Twinfield.** It deletes the currency again, but only run it on a test administration.

Creates a currency (the code you pass, or the first unused code from `ZZZ` down) and deletes it.

`delete($code)` sets the status of the currency to `deleted`. It throws a `CurrencyNotFoundException` when the code does not exist, and an `UnexpectedResponseException` when Twinfield does not confirm the deletion.

To delete one exchange rate instead of the whole currency, use `removeRate()` (see [Update currency rates](../update-currency-rates/)).

## Code

```php
$twinfield = Example::twinfield();

$currencies = $twinfield->currencies();
$code = $argv[1] ?? Example::unusedCurrencyCode($twinfield);

$currencies->create(CurrencyInput::create($code, 'Example currency'));

echo 'Exists: ', $currencies->exists($code) ? 'yes' : 'no', PHP_EOL;

$currencies->delete($code);

echo 'Deleted ', $code, PHP_EOL;
echo 'Exists: ', $currencies->exists($code) ? 'yes' : 'no', PHP_EOL;
```

## Run

```sh
php examples/currencies/delete-currency/example.php
```

```text
Exists: yes
Deleted ZZZ
Exists: no
```
