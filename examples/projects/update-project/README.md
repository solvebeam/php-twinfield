# Update project

> **This example changes data in Twinfield.** It deletes the project again, but only run it on a test administration.

Creates a project (the code you pass, or the first unused code from `P9999` down), renames it and sets an end date, and then deletes it.

To change a project, start from what Twinfield has: `$project->edit()` gives a `ProjectInput` with all current values. Change what you need and pass it to `update()`. The SDK always sends all values, so a value you remove (e.g. `shortName(null)`) is also removed in Twinfield.

`update()` throws a `DimensionNotFoundException` when the project does not exist.

## Code

```php
$twinfield = Example::twinfield();

$projects = $twinfield->projects();
$code = $argv[1] ?? Example::unusedProjectCode($twinfield);

$projects->create(ProjectInput::create($code, 'Example project'));

try {
    $project = $projects->get($code);
    echo 'Before: ', $project->name, PHP_EOL;

    $projects->update(
        $project->edit()
            ->name('Example project (updated)')
            ->validTill(new DateTimeImmutable('2030-12-31')),
    );

    $project = $projects->get($code);
    echo 'After:  ', $project->name, ', valid till ', $project->projects->validTill?->format('Y-m-d'), PHP_EOL;
} finally {
    $projects->delete($code);
}
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/projects/update-project/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
Before: Example project
After:  Example project (updated), valid till 2030-12-31
```
