# Update VAT percentage

> **This example changes data in Twinfield.** It creates a VAT code and deletes it again, but only run it on a test administration.

Gives a VAT code a new rate from a date, the most common change to a VAT code, like the Dutch low rate that went from 6% to 9% in 2019. It creates an example VAT code of 6% from 2018, adds 9% from 2019, shows the percentages and the VAT on 100.00 in both years, and deletes the VAT code again.

Start from `$vatCode->edit()`: it has all values, percentages and accounts of the VAT code. Then change what you need:

| Method | What it does |
|---|---|
| `newPercentageFrom($from, '9', 'BTW 9%', 'V 9%')` | Adds the rate from `$from`, with the accounts of the percentage that applies the day before. Name and short name are copied too, unless you give them. |
| `percentage(VatPercentageInput)` | Adds a percentage, or replaces the one with the same start date. |
| `percentageFrom($date)` | The percentage with this start date, e.g. to change its accounts and pass it to `percentage()` again. |
| `name()`, `shortName()`, `type()` | Other values. |

`newPercentageFrom()` throws an `InvalidArgumentException` when no percentage starts before the date. Nothing is sent then.

## Code

```php
$twinfield = Example::twinfield();

$vatCodes = $twinfield->vatCodes();

// 9% from 2019, on the same accounts as the 6%.
$vatCode = $vatCodes->update(
    $vatCode->edit()->newPercentageFrom(new DateTimeImmutable('2019-01-01'), '9', 'Example VAT 9%'),
);

echo 'VAT on 100.00 in 2018: ', $vatCode->vatOn('100.00', new DateTimeImmutable('2018-06-01')), PHP_EOL;
echo 'VAT on 100.00 in 2019: ', $vatCode->vatOn('100.00', new DateTimeImmutable('2019-06-01')), PHP_EOL;
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/vat-codes/update-vat-percentage/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
2018-01-01: 6% Example VAT 6%
2019-01-01: 9% Example VAT 9%
VAT on 100.00 in 2018: 6.00
VAT on 100.00 in 2019: 9.00
Deleted EXAMPLE999
```
