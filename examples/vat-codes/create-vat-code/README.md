# Create VAT code

> **This example changes data in Twinfield.** It deletes the VAT code again, but only run it on a test administration.

Creates a sales VAT code of 21% from 2026, posted on the VAT payable account of the company in box 1a of the Dutch VAT return, and deletes it again. It uses the code you pass, or else the first unused code from `EXAMPLE999` down (`Example::unusedVatCode()`).

Describe the VAT code with `VatCodeInput`, each percentage with `VatPercentageInput` and each account with `VatAccountInput`. Every method returns a new input:

| Method | What it sets |
|---|---|
| `VatCodeInput::sales($code, $name)`, `VatCodeInput::purchase($code, $name)` | A VAT code for sales or purchases; code (A-Z, 0-9, `_`, `-`, `+`) and name (at most 40 characters). |
| `shortName()` | Short name, at most 20 characters; `null` removes it. |
| `percentage(VatPercentageInput)` | Adds a percentage, or replaces the one with the same start date. |
| `VatPercentageInput::from($date, '21')` | The rate from a date on; `name()` and `shortName()` of the line. Without name, the line gets the name of the VAT code. |
| `->account(VatAccountInput)`, `->withAccounts(...)` | Adds an account, or replaces all accounts. |
| `VatAccountInput::vat('1530')` | All VAT on this account; `VatAccountInput::vat('1530', '-100')` for the negative half of reverse charge. |
| `VatAccountInput::detail('4300', '30')` | A share of the VAT on another account, e.g. VAT that is not deductible. |
| `->group('NL', 'NL1A')` | The VAT group: the box of the VAT return. |

Percentages are numeric strings or `Percentage` objects, never floats. The SDK checks that a rate is 0 to 100, that an account share is -100 to 100, and that every percentage has an account. Invalid input throws before anything is sent.

Twinfield itself has one call for create and update. The SDK makes the difference explicit:

- `create()` reads first and throws a `VatCodeAlreadyExistsException` when the code exists, so you never overwrite a VAT code by accident.
- `update()` throws a `VatCodeNotFoundException` when the code does not exist (see [Update VAT percentage](../update-vat-percentage/)).
- `save()` does what Twinfield does, without the check.

All three read the VAT code back afterwards, so you get the complete `VatCode`. When Twinfield refuses it, for example because the account does not exist, you get a `ProcessXmlException` with the message per element.

## Code

```php
$twinfield = Example::twinfield();

$vatCodes = $twinfield->vatCodes();

$vatCode = $vatCodes->create(
    VatCodeInput::sales($code, 'Example VAT 21%')
        ->shortName('Example 21%')
        ->percentage(
            VatPercentageInput::from(new DateTimeImmutable('2026-01-01'), '21')
                ->account(VatAccountInput::vat($account)->group('NL', 'NL1A')),
        ),
);

// Clean up: delete the example VAT code again.
$vatCodes->delete($vatCode->code);
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/vat-codes/create-vat-code/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
Created EXAMPLE999 Example VAT 21% (sales): 21% from 2026-01-01
Deleted EXAMPLE999
```
