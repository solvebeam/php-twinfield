# Delete project

> **This example changes data in Twinfield.** It deletes the project again, but only run it on a test administration.

Creates a project (the code you pass, or the first unused code from `P9999` down) and deletes it.

A project that is used in a transaction cannot be deleted: Twinfield hides it instead. `delete()` tells you which one happened:

- `DeleteResult::Deleted`: the project is deleted (status `deleted`).
- `DeleteResult::Hidden`: the project is in use and now hidden (status `hide`).

`activate($code)` makes a deleted or hidden project active again.

## Code

```php
$twinfield = Example::twinfield();

$projects = $twinfield->projects();
$code = $argv[1] ?? Example::unusedProjectCode($twinfield);

$projects->create(ProjectInput::create($code, 'Example project'));

$result = $projects->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;

echo 'Status now: ', $projects->get($code)->status->value, PHP_EOL;
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/projects/delete-project/example.php
```

`composer test:write` also runs this example, after you confirm the company code.

```text
Deleted P9999.
Status now: deleted
```
