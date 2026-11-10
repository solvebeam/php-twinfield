# Create project

> **This example changes data in Twinfield.** It deletes the project again, but only run it on a test administration.

Creates a project and deletes it again. It uses the code you pass, or else the first unused code from `P9999` down (`Example::unusedProjectCode()`): a deleted project keeps its code, so every run needs a new one.

Describe the project with `ProjectInput`. Every method returns a new input, and everything you leave out is inherited from the activity or user level:

| Method | What it sets |
|---|---|
| `ProjectInput::create($code, $name)` | Code in the code format of the company (by default `P[0-9][0-9][0-9][0-9]`) and a name of at most 80 characters. |
| `shortName()`, `invoiceDescription()` | Short name (20), text for the invoice (128). |
| `vatCode()` | VAT code for all activities of the project, unless an activity has its own. |
| `validFrom()`, `validTill()` | The dates users can book hours on it. |
| `customer()`, `rate()`, `authoriser()` | A code and a `ChangeMode`, e.g. `customer('1010', ChangeMode::Disallow)`. |
| `billable(true, ChangeMode::Allow, forRatio: true)` | Billable hours, and whether they count for the productivity ratio. |
| `quantity(Quantity::create('KM')->rate('KM')->mandatory())` | An extra quantity on timesheet lines. |

Twinfield links every project to a customer: set it with `customer()`, or leave it inherited so the customer of the activity is used.

Twinfield itself has one call for create and update, which creates the project when the code is new and changes it otherwise. The SDK makes the difference explicit:

- `create()` reads first and throws a `DimensionAlreadyExistsException` when the code exists, so you never overwrite a project by accident.
- `update()` throws a `DimensionNotFoundException` when the code does not exist (see [Update project](../update-project/)).
- `save()` does what Twinfield does, in one request.

Invalid input (a name that is too long, a code with lowercase letters) throws an `InvalidArgumentException` before anything is sent. When Twinfield refuses the project, you get a `ProcessXmlException` with the message per element (see [Read dimension](../../process-xml/read-dimension/)).

## Code

```php
$twinfield = Example::twinfield();

$projects = $twinfield->projects();
$code = $argv[1] ?? Example::unusedProjectCode($twinfield);

$project = $projects->create(
    ProjectInput::create($code, 'Example project: website redesign and API documentation')
        ->shortName('Example')
        ->invoiceDescription('Created by the twinfield-php examples')
        ->validFrom(new DateTimeImmutable('first day of january this year'))
        ->validTill(new DateTimeImmutable('last day of december this year'))
        ->billable(true, ChangeMode::Disallow)
        ->quantity(Quantity::create('KM')->billable(false)),
);

echo 'Created ', $project->code, ' ', $project->name, ' (', $project->uid, ')', PHP_EOL;

// Clean up: delete the example project again.
$projects->delete($project->code);

echo 'Deleted ', $project->code, PHP_EOL;
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/projects/create-project/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
Created P9999 Example project: website redesign and API documentation (8c1d7a52-3f0e-4b9a-9d55-2e6f1b0c7a11)
Deleted P9999
```
