# Read rate

Reads one rate with all its prices: the code you pass, or else the first one. It also shows the price that applies today.

`get()` returns a `Rate`; `find()` returns `null` instead of throwing when the code does not exist.

| Property | What it holds |
|---|---|
| `code`, `name`, `shortName` | The rate; `shortName` is `null` when not set. |
| `status`, `inUse` | `RateStatus` (active, deleted, hidden), and whether activities, projects or timesheets use it. |
| `type` | `RateType::Time` for hours, `RateType::Quantity` for quantities such as kilometres. |
| `unit` | The number of units the prices are for, as set in Twinfield. |
| `currency` | Currency code, e.g. `EUR`. |
| `prices` | The `RatePrice` lines, oldest first: `from`, `until` (`null` without end date), `internal` (cost price) and `external` (selling price) per unit, and the line `id`. |
| `priceOn($date)`, `latestPrice()` | The price on a date (`null` when no line covers it), and the line with the latest start date. |
| `created`, `modified`, `modifiedBy`, `touched` | When and by whom it was changed, and how often. |

Prices are `UnitPrice` objects: numeric strings with four decimals, as Twinfield keeps them (`'0.1550'`), never floats. Twinfield stores "no end date" as 2999-12-31; the SDK gives `null` for that.

When Twinfield returns something the SDK does not expect, such as an unknown rate type or a price with a comma, you get an `UnexpectedResponseException` with the path of the element, instead of a silent default.

## Code

```php
$twinfield = Example::twinfield();

try {
    $rate = $twinfield->rates()->get($code);
} catch (RateNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

foreach ($rate->prices as $price) {
    echo 'Price:     ', $price->from->format('Y-m-d'), ' to ', $price->until?->format('Y-m-d') ?? '…', ': ';
    echo 'internal ', $price->internal, ', external ', $price->external, PHP_EOL;
}

$today = $rate->priceOn(new DateTimeImmutable('today'));

echo 'Today:     ', $today === null ? 'no price' : $today->external, PHP_EOL;
```

## Run

```sh
php examples/rates/read-rate/example.php
php examples/rates/read-rate/example.php CONSULT
```

```text
Code:      CONSULT
Name:      Consultancy
Status:    active (in use)
Type:      time
Unit:      1
Currency:  EUR
Price:     2025-01-01 to 2026-06-30: internal 55.0000, external 95.0000
Price:     2026-07-01 to …: internal 60.0000, external 105.0000
Today:     105.0000
```
