# Create activity

> **This example changes data in Twinfield.** It deletes the activity again, but only run it on a test administration.

Creates an activity and deletes it again. It uses the code you pass, or else the first unused code from `A999` down (`Example::unusedActivityCode()`): a deleted activity keeps its code, so every run needs a new one.

Describe the activity with `ActivityInput`. Every method returns a new input, and everything you leave out is inherited from the project or user level:

| Method | What it sets |
|---|---|
| `ActivityInput::create($code, $name)` | Code in the code format of the company (by default `A[0-9][0-9][0-9]`) and a name of at most 40 characters. |
| `shortName()`, `invoiceDescription()`, `vatCode()` | Short name (20), text for the invoice (128), VAT code. |
| `validFrom()`, `validTill()` | The dates users can book hours on it. |
| `customer()`, `rate()`, `authoriser()` | A code and a `ChangeMode`, e.g. `rate('T001', ChangeMode::Disallow)`. |
| `billable(true, ChangeMode::Allow, forRatio: true)` | Billable hours, and whether they count for the productivity ratio. |
| `quantity(Quantity::create('KM')->rate('KM')->mandatory())` | An extra quantity on timesheet lines. |

Twinfield itself has one call for create and update, which creates the activity when the code is new and changes it otherwise. The SDK makes the difference explicit:

- `create()` reads first and throws a `DimensionAlreadyExistsException` when the code exists, so you never overwrite an activity by accident.
- `update()` throws a `DimensionNotFoundException` when the code does not exist (see [Update activity](../update-activity/)).
- `save()` does what Twinfield does, in one request.

Invalid input (a name that is too long, a code with lowercase letters) throws an `InvalidArgumentException` before anything is sent. When Twinfield refuses the activity, you get a `ProcessXmlException` with the message per element (see [Read dimension](../../process-xml/read-dimension/)).

## Code

```php
$twinfield = Example::twinfield();

$activities = $twinfield->activities();
$code = $argv[1] ?? Example::unusedActivityCode($twinfield);

$activity = $activities->create(
    ActivityInput::create($code, 'Example activity')
        ->shortName('Example')
        ->invoiceDescription('Created by the php-twinfield examples')
        ->billable(true, ChangeMode::Disallow)
        ->quantity(Quantity::create('KM')->billable(false)),
);

echo 'Created ', $activity->code, ' ', $activity->name, ' (', $activity->uid, ')', PHP_EOL;

// Clean up: delete the example activity again.
$activities->delete($activity->code);

echo 'Deleted ', $activity->code, PHP_EOL;
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/activities/create-activity/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
Created A999 Example activity (8c1d7a52-3f0e-4b9a-9d55-2e6f1b0c7a11)
Deleted A999
```
