# Delete activity

> **This example changes data in Twinfield.** It deletes the activity again, but only run it on a test administration.

Creates an activity (the code you pass, or the first unused code from `A999` down) and deletes it.

An activity that is used in a transaction cannot be deleted: Twinfield hides it instead. `delete()` tells you which one happened:

- `DeleteResult::Deleted`: the activity is deleted (status `deleted`).
- `DeleteResult::Hidden`: the activity is in use and now hidden (status `hide`).

`activate($code)` makes a deleted or hidden activity active again.

## Code

```php
$twinfield = Example::twinfield();

$activities = $twinfield->activities();
$code = $argv[1] ?? Example::unusedActivityCode($twinfield);

$activities->create(ActivityInput::create($code, 'Example activity'));

$result = $activities->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;

echo 'Status now: ', $activities->get($code)->status->value, PHP_EOL;
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/activities/delete-activity/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
Deleted A999.
Status now: deleted
```
