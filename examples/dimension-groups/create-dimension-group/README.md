# Create dimension group

> **This example changes data in Twinfield.** It deletes the group again, but only run it on a test administration.

Creates a dimension group (the code you pass, or the first unused code from `EXAMPLE-999` down) with the first three customers of the company, and deletes it again.

`DimensionGroupInput` is immutable: every method returns a new input. `add('DEB', '1000', '1001')` adds dimensions of one type; a dimension that is already in the input is not added twice. The dimensions must exist: Twinfield refuses an unknown code with a `ProcessXmlException` with the message on that dimension, e.g. `dimensiongroup/dimensions/dimension[2]/code`. The name has at most 40 characters, the short name at most 20.

`create()` first checks that the code is not in use, and throws a `DimensionGroupAlreadyExistsException` when it is. The code of a deleted group can be used again. `save()` creates or replaces without that check. Both return the group as Twinfield saved it.

## Code

```php
$twinfield = Example::twinfield();

$groups = $twinfield->dimensionGroups();
$code = $argv[1] ?? Example::unusedDimensionGroupCode($twinfield);

// Three existing customers (dimension type DEB) as members.
$customers = Example::customerCodes($twinfield, 3);

$group = $groups->create(
    DimensionGroupInput::create($code, 'Example key accounts')
        ->shortName('Key accounts')
        ->add('DEB', ...$customers),
);

try {
    echo 'Created ', $group->code, ' (', $group->name, ')', PHP_EOL;

    foreach ($group->dimensions as $dimension) {
        echo '  ', $dimension->type, ' ', $dimension->code, '  ', $dimension->name ?? '-', PHP_EOL;
    }
} finally {
    // Clean up.
    $groups->delete($code);
}
```

## Run

```sh
php examples/dimension-groups/create-dimension-group/example.php
```

```text
Created EXAMPLE-999 (Example key accounts)
  DEB 1000  Example customer
  DEB 1001  Another customer
  DEB 1002  Third customer
```
