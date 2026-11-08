# Create rate

> **This example changes data in Twinfield.** It deletes the rate again, but only run it on a test administration.

Creates a rate for kilometres with a cost price of 0.15 and a selling price of 0.23 per kilometre, and deletes it again. It uses the code you pass, or else the first unused code from `EXAMPLE999` down (`Example::unusedRateCode()`).

Describe the rate with `RateInput`. Every method returns a new input:

| Method | What it sets |
|---|---|
| `RateInput::time($code, $name)`, `RateInput::quantity($code, $name)` | A rate for hours or for quantities; code (A-Z, 0-9, `_`, `-`, `+`) and name (at most 40 characters). |
| `shortName()` | Short name, at most 20 characters; `null` removes it. |
| `unit(8)` | The number of units the prices are for; 1 by default (per hour, per kilometre). |
| `currency('EUR')` | Currency code; not sent when not set. |
| `price($from, internal: '0.15', external: '0.23')` | Cost and selling price per unit from a date, without end date. |
| `price($from, '0.15', '0.23', $until)` | The same, until an end date. |

Prices are numeric strings or `UnitPrice` objects with at most four decimals, never floats. Twinfield requires at least one price. The SDK checks that prices do not overlap and do not end before they start. Invalid input throws an `InvalidArgumentException` before anything is sent.

Twinfield itself has one call for create and update. The SDK makes the difference explicit:

- `create()` reads first and throws a `RateAlreadyExistsException` when the code exists, so you never overwrite a rate by accident.
- `update()` throws a `RateNotFoundException` when the code does not exist (see [Update rate price](../update-rate-price/)).
- `save()` does what Twinfield does, without the check.

All three read the rate back afterwards, so you get the complete `Rate`. When Twinfield refuses the rate, for example because the currency does not exist, you get a `ProcessXmlException` with the message per element.

## Code

```php
$twinfield = Example::twinfield();

$rates = $twinfield->rates();
$code = $argv[1] ?? Example::unusedRateCode($twinfield);

$rate = $rates->create(
    RateInput::quantity($code, 'Example kilometres')
        ->shortName('Example km')
        ->currency('EUR')
        ->price(new DateTimeImmutable('2026-01-01'), internal: '0.15', external: '0.23'),
);

echo 'Created ', $rate->code, ' ', $rate->name, ' (', $rate->type->value, '): ';
echo $rate->latestPrice()->external, ' ', $rate->currency, ' per unit', PHP_EOL;

// Clean up: delete the example rate again.
$rates->delete($rate->code);
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/rates/create-rate/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
Created EXAMPLE999 Example kilometres (quantity): 0.2300 EUR per unit
Deleted EXAMPLE999
```
