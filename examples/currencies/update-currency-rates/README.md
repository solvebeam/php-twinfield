# Update currency rates

> **This example changes data in Twinfield.** It deletes the currency again, but only run it on a test administration.

Creates a currency with two exchange rates, then adds a rate, changes one and removes one in a single update.

Start from `$currency->edit()`: it has the name, short name and all current rates. On that input:

- `rate($startDate, $rate)` adds a rate, or changes the rate with that start date.
- `removeRate($startDate)` deletes the rate with that start date (Twinfield: `rate/@status="deleted"`).

`update()` sends the currency with all its rates, so the rates you did not touch stay as they are. It throws a `CurrencyNotFoundException` when the currency does not exist.

## Code

```php
$twinfield = Example::twinfield();

$currencies = $twinfield->currencies();
$code = $argv[1] ?? Example::unusedCurrencyCode($twinfield);

$print = static function (string $label, Currency $currency): void {
    echo $label, PHP_EOL;

    foreach ($currency->rates as $rate) {
        echo '  from ', $rate->startDate->format('Y-m-d'), '  ', $rate->rate, PHP_EOL;
    }
};

$currency = $currencies->create(
    CurrencyInput::create($code, 'Example currency')
        ->rate(new DateTimeImmutable('2026-01-01'), '1.0850')
        ->rate(new DateTimeImmutable('2026-04-01'), '1.0790'),
);

try {
    $print('Before:', $currency);

    $currency = $currencies->update(
        $currency->edit()
            ->rate(new DateTimeImmutable('2026-07-01'), '1.0912')   // add
            ->rate(new DateTimeImmutable('2026-01-01'), '1.0855')   // change
            ->removeRate(new DateTimeImmutable('2026-04-01')),      // remove
    );

    $print('After:', $currency);
} finally {
    // Clean up.
    $currencies->delete($code);
}
```

## Run

```sh
php examples/currencies/update-currency-rates/example.php
```

```text
Before:
  from 2026-01-01  1.085
  from 2026-04-01  1.079
After:
  from 2026-01-01  1.0855
  from 2026-07-01  1.0912
```
