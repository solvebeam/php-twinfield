# Read currency

Reads one currency with its exchange rates: the code from the command line, or else the first currency.

`get()` returns a `Currency`, or throws a `CurrencyNotFoundException` when the code does not exist. Use `find()` to get `null` instead.

A currency has a `name`, a `shortName` (`null` when not set), a `status` and its `rates`: each a `CurrencyRate` with a `startDate` and a `rate`, oldest first. A rate applies from its start date until the start date of the next one. `rateOn($date)` gives the rate that applies on a date, `latestRate()` the newest one; both return `null` when there is none.

Rates are `ExchangeRate` objects: numeric strings with all their decimals, never floats.

Twinfield has no read request for currencies: the SDK checks in the Finder that the code exists, and then sends a `<currency>` with only the company and the code, which returns the currency. That way a read never creates a currency. The test write guard counts this request as a write, so `composer test:live` skips this example.

## Code

```php
$twinfield = Example::twinfield();

// The code from the command line, or else the first currency.
$code = $argv[1] ?? $twinfield->currencies()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no currencies.' . PHP_EOL);

try {
    $currency = $twinfield->currencies()->get($code);
} catch (CurrencyNotFoundException) {
    echo 'Currency ', $code, ' not found.', PHP_EOL;

    exit(1);
}

echo 'Code:        ', $currency->code, PHP_EOL;
echo 'Name:        ', $currency->name, PHP_EOL;
echo 'Short name:  ', $currency->shortName ?? '-', PHP_EOL;
echo 'Status:      ', $currency->status->value, PHP_EOL;
echo PHP_EOL;

echo 'Rates:', PHP_EOL;

foreach ($currency->rates as $rate) {
    echo '  from ', $rate->startDate->format('Y-m-d'), '  ', $rate->rate, PHP_EOL;
}

echo PHP_EOL;
echo 'Rate today:  ', $currency->rateOn(new DateTimeImmutable('today'))?->rate ?? '-', PHP_EOL;
```

## Run

```sh
php examples/currencies/read-currency/example.php
```

```text
Code:        USD
Name:        US dollar
Short name:  $
Status:      active

Rates:
  from 2026-01-01  1.085
  from 2026-07-01  1.0912

Rate today:  1.0912
```
