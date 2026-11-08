# Delete rate

> **This example changes data in Twinfield.** It creates a rate first, but only run it on a test administration.

Creates an example rate and deletes it again.

`delete()` returns a `DeleteResult`:

- `DeleteResult::Deleted`: the rate is deleted.
- `DeleteResult::Hidden`: activities, projects or timesheets use the rate, so Twinfield hid it instead.

`activate()` makes a deleted or hidden rate active again. Both throw a `RateNotFoundException` when the code does not exist.

## Code

```php
$twinfield = Example::twinfield();

$rates = $twinfield->rates();

$result = $rates->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/rates/delete-rate/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
Deleted EXAMPLE999.
Status now: deleted
```
