# Update dimension group

> **This example changes data in Twinfield.** It creates a temporary group and deletes it again; only run it on a test administration.

Renames a dimension group, adds a customer and removes one.

Saving a group replaces its name, short name and **all** its dimensions. So start from `$group->edit()`: it has all current values and dimensions, and only what you change with `name()`, `shortName()`, `add()`, `remove()` or `clear()` differs. `update()` first checks that the group exists and throws a `DimensionGroupNotFoundException` when it does not.

## Code

```php
$twinfield = Example::twinfield();

$groups = $twinfield->dimensionGroups();
$code = Example::unusedDimensionGroupCode($twinfield);
[$first, $second, $third] = Example::customerCodes($twinfield, 3);

// A temporary group with two customers.
$groups->create(DimensionGroupInput::create($code, 'Example key accounts')->add('DEB', $first, $second));

try {
    $group = $groups->get($code);

    echo 'Before: ', $group->name, ' [', implode(', ', $group->codes()), ']', PHP_EOL;

    // edit() has all current values and dimensions: only the changes differ.
    $group = $groups->update(
        $group->edit()
            ->name('Example key accounts 2026')
            ->add('DEB', $third)
            ->remove('DEB', $first),
    );

    echo 'After:  ', $group->name, ' [', implode(', ', $group->codes()), ']', PHP_EOL;
} finally {
    // Clean up.
    $groups->delete($code);
}
```

## Run

```sh
php examples/dimension-groups/update-dimension-group/example.php
```

```text
Before: Example key accounts [1000, 1001]
After:  Example key accounts 2026 [1001, 1002]
```
