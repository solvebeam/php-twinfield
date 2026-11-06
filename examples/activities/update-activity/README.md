# Update activity

> **This example changes data in Twinfield.** It deletes the activity again, but only run it on a test administration.

Creates an activity (the code you pass, or the first unused code from `A999` down), renames it and sets an end date, and then deletes it.

To change an activity, start from what Twinfield has: `$activity->edit()` gives an `ActivityInput` with all current values. Change what you need and pass it to `update()`. The SDK always sends all values, so a value you remove (e.g. `shortName(null)`) is also removed in Twinfield.

`update()` throws a `DimensionNotFoundException` when the activity does not exist.

## Code

```php
$twinfield = Example::twinfield();

$activities = $twinfield->activities();
$code = $argv[1] ?? Example::unusedActivityCode($twinfield);

$activities->create(ActivityInput::create($code, 'Example activity'));

try {
    $activity = $activities->get($code);
    echo 'Before: ', $activity->name, PHP_EOL;

    $activities->update(
        $activity->edit()
            ->name('Example activity (updated)')
            ->validTill(new DateTimeImmutable('2030-12-31')),
    );

    $activity = $activities->get($code);
    echo 'After:  ', $activity->name, ', valid till ', $activity->projects->validTill?->format('Y-m-d'), PHP_EOL;
} finally {
    $activities->delete($code);
}
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/activities/update-activity/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
Before: Example activity
After:  Example activity (updated), valid till 2030-12-31
```
