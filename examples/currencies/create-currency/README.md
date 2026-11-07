# Create currency

> **This example changes data in Twinfield.** It deletes the currency again, but only run it on a test administration.

Creates a currency (the code you pass, or the first unused code from `ZZZ` down) with two exchange rates, and deletes it again.

`CurrencyInput` is immutable: every method returns a new input. `rate($startDate, $rate)` adds a rate, or replaces the rate with the same start date. Pass rates as numeric strings (`'1.0850'`) or `ExchangeRate` objects, never floats.

`create()` first checks that the code is not in use, and throws a `CurrencyAlreadyExistsException` when it is. `save()` creates or updates without that check. Both read the currency back, so you get it with all its rates.

## Code

```php
$twinfield = Example::twinfield();

$currencies = $twinfield->currencies();
$code = $argv[1] ?? Example::unusedCurrencyCode($twinfield);

$currency = $currencies->create(
    CurrencyInput::create($code, 'Example currency')
        ->shortName('¤')
        ->rate(new DateTimeImmutable('2026-01-01'), '1.0850')
        ->rate(new DateTimeImmutable('2026-07-01'), '1.0912'),
);

try {
    echo 'Created ', $currency->code, ' (', $currency->name, ')', PHP_EOL;

    foreach ($currency->rates as $rate) {
        echo '  from ', $rate->startDate->format('Y-m-d'), '  ', $rate->rate, PHP_EOL;
    }
} finally {
    // Clean up.
    $currencies->delete($code);
}
```

## Run

```sh
php examples/currencies/create-currency/example.php
```

```text
Created ZZZ (Example currency)
  from 2026-01-01  1.085
  from 2026-07-01  1.0912
```
