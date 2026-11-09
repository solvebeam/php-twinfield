# Read VAT code

Reads one VAT code with all its percentages and accounts: the code you pass, or else the first one. It also shows the percentage that applies today, and the VAT on 100.00.

`get()` returns a `VatCode`; `find()` returns `null` instead of throwing when the code does not exist.

| Property | What it holds |
|---|---|
| `code`, `name`, `shortName` | The VAT code; `shortName` is `null` when not set. |
| `type` | `VatCodeType::Sales` or `VatCodeType::Purchase`. |
| `status` | `VatCodeStatus` (active, deleted, hidden). |
| `percentages` | The `VatPercentage` lines, oldest first. Each applies from its `from` date until the next one starts. |
| `percentageOn($date)`, `currentPercentage()`, `latestPercentage()` | The line that applies on a date or today (`null` before the first one), and the line with the latest start date. |
| `vatOn($amount, $date)` | The VAT on an amount without VAT, rounded to cents: `vatOn('100.00', $date)` gives `21.00`. |
| `uid`, `created`, `modified`, `modifiedBy`, `touched` | Unique id, when and by whom it was changed, and how often. |

A `VatPercentage` has the rate (`percentage`), `name`, `shortName`, `inUse`, `created`, `createdBy` and its `accounts`. A `VatAccount` is where (a share of) the VAT is posted:

| Property | What it holds |
|---|---|
| `generalLedgerAccount` | The account (`dim1`), with code and name. |
| `groupCountry`, `group` | The VAT group: the box of the VAT return, e.g. `NL` and `NL1A`; `null` when not set. |
| `percentage` | The share of the VAT, e.g. `100`. Reverse charge codes have `-100` on one account and `100` on another. |
| `lineType` | `VatAccountLineType::Vat`, or `Detail` for a part of the VAT posted on another account. |
| `id` | The line id. |

Percentages are `Percentage` objects: numeric strings without trailing zeros (`21`, `5.5`), never floats. Twinfield sends rates with nine decimals (`21.000000000`).

## Code

```php
$twinfield = Example::twinfield();

try {
    $vatCode = $twinfield->vatCodes()->get($code);
} catch (VatCodeNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

foreach ($vatCode->percentages as $percentage) {
    echo 'From ', $percentage->from->format('Y-m-d'), ': ', $percentage->percentage, '% ', $percentage->name, PHP_EOL;

    foreach ($percentage->accounts as $account) {
        echo '  ', $account->percentage, '% on ', $account->generalLedgerAccount->code;
        echo ' (', $account->generalLedgerAccount->name ?? '', ')';
        echo $account->group === null ? '' : ', VAT group ' . $account->group->code, PHP_EOL;
    }
}

$today = new DateTimeImmutable('today');

echo 'VAT on 100.00 today: ', $vatCode->vatOn('100.00', $today), PHP_EOL;
```

## Run

```sh
php examples/vat-codes/read-vat-code/example.php
php examples/vat-codes/read-vat-code/example.php ICV-H
```

A reverse charge code (intra-community acquisitions): the VAT is both payable and deductible.

```text
Code:      ICV-H
Name:      IC verwervingen binnnen EU
Type:      sales
Status:    active
From 2010-01-01: 19% IC verwervingen binnnen EU
  -100% on 16626 (Verwervingen van goederen uit landen binnen de EU), VAT group NL4B
  100% on 16627 (BTW Voorbelasting), VAT group NL5B
From 2012-10-01: 21% IC verwervingen binnnen EU
  -100% on 16626 (Verwervingen van goederen uit landen binnen de EU), VAT group NL4B
  100% on 16627 (BTW Voorbelasting), VAT group NL5B
Today:     21% VAT
VAT on 100.00 today: 21.00
```
