# Delete dimension group

> **This example changes data in Twinfield.** Only run it on a test administration.

Deletes a dimension group: the code you pass, or else a temporary group it creates first.

`delete()` first empties the group and then sets its status to `deleted`. Twinfield keeps the code of a deleted group on its dimensions (e.g. in the groups of a customer), so emptying it first means no dimension still refers to it. The dimensions do not change otherwise. A deleted group is no longer in the search results, and `get()` and `exists()` treat it as unknown. The code can be used again: `create()` then makes the group active with the new values.

`delete()` first checks in the Finder that the group exists, because Twinfield would create an unknown code to delete it. It throws a `DimensionGroupNotFoundException` when the group does not exist.

## Code

```php
$twinfield = Example::twinfield();

$groups = $twinfield->dimensionGroups();

// The code from the command line, or else a temporary group to delete.
$code = $argv[1] ?? $groups->create(
    DimensionGroupInput::create(Example::unusedDimensionGroupCode($twinfield), 'Example group to delete'),
)->code;

try {
    $groups->delete($code);
} catch (DimensionGroupNotFoundException) {
    echo 'Dimension group ', $code, ' not found.', PHP_EOL;

    exit(1);
}

echo 'Deleted ', $code, PHP_EOL;
echo 'Exists:  ', $groups->exists($code) ? 'yes' : 'no', PHP_EOL;
```

## Run

```sh
php examples/dimension-groups/delete-dimension-group/example.php
```

```text
Deleted EXAMPLE-999
Exists:  no
```
