# Update rate price

> **This example changes data in Twinfield.** It creates a rate and deletes it again, but only run it on a test administration.

Gives a rate new prices from a date, the most common change to a rate. It creates an example rate of 95 per hour from January, raises it to 105 from July, shows the prices and deletes the rate again.

Start from `$rate->edit()`: it has all values and prices of the rate, each price with its line id, so the update changes those lines instead of adding them again. Then change what you need:

| Method | What it does |
|---|---|
| `newPriceFrom($from, internal: '60', external: '105')` | The price without end date ends the day before `$from`, and the new prices apply from `$from` without end date. On the start date of the current price, it replaces that price. |
| `price($from, '60', '105', $until)` | Adds a price, or changes the price with the same start date. |
| `removePrice($from)` | Deletes the price with this start date. |
| `name()`, `shortName()`, `unit()`, `currency()`, `type()` | Other values. |

`newPriceFrom()` throws an `InvalidArgumentException` when the current price starts after the date, and `price()` throws one when the prices would overlap. Nothing is sent then.

## Code

```php
$twinfield = Example::twinfield();

$rates = $twinfield->rates();

// New prices from July: the current price ends on June 30.
$rate = $rates->update(
    $rate->edit()->newPriceFrom(new DateTimeImmutable('2026-07-01'), internal: '60', external: '105'),
);

foreach ($rate->prices as $price) {
    echo $price->from->format('Y-m-d'), ' to ', $price->until?->format('Y-m-d') ?? '…', ': ';
    echo $price->external, ' ', $rate->currency, PHP_EOL;
}
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/rates/update-rate-price/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
2026-01-01 to 2026-06-30: 95.0000 EUR
2026-07-01 to …: 105.0000 EUR
Deleted EXAMPLE999
```
