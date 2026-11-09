# Delete VAT code

> **This example changes data in Twinfield.** It creates a VAT code first, but only run it on a test administration.

Creates an example VAT code and deletes it again.

`delete()` returns a `DeleteResult`:

- `DeleteResult::Deleted`: the VAT code is deleted.
- `DeleteResult::Hidden`: transactions use the VAT code, so Twinfield hid it instead.

`activate()` makes a deleted or hidden VAT code active again. Both throw a `VatCodeNotFoundException` when the code does not exist.

## Code

```php
$twinfield = Example::twinfield();

$vatCodes = $twinfield->vatCodes();

$result = $vatCodes->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/vat-codes/delete-vat-code/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
Deleted EXAMPLE999.
Status now: deleted
```
