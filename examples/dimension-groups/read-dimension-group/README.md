# Read dimension group

Reads one dimension group with its dimensions: the code from the command line, or else the first group.

`get()` returns a `DimensionGroup`, or throws a `DimensionGroupNotFoundException` when the code does not exist (or the group is deleted). Use `find()` to get `null` instead.

A group has a `name`, a `shortName` (`null` when not set), a `status` and its `dimensions`: each a `DimensionGroupDimension` with the `type` (e.g. `DEB`), the `code` and the `name` Twinfield adds. A group can have dimensions of more than one type. `types()` lists them, `codes('DEB')` gives the codes of one type, and `has('DEB', '1000')` checks for one dimension.

Twinfield has no read request for dimension groups, and a `<dimensiongroup>` request for an unknown code creates the group. So the SDK first checks in the Finder that the code exists, and then sends a `<dimensiongroup>` with only the company and the code, which returns the group. That way a read never creates a group. The test write guard counts this request as a write, so `composer test:live` skips this example.

## Code

```php
$twinfield = Example::twinfield();

// The code from the command line, or else the first dimension group.
$code = $argv[1] ?? $twinfield->dimensionGroups()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no dimension groups.' . PHP_EOL);

try {
    $group = $twinfield->dimensionGroups()->get($code);
} catch (DimensionGroupNotFoundException) {
    echo 'Dimension group ', $code, ' not found.', PHP_EOL;

    exit(1);
}

echo 'Code:        ', $group->code, PHP_EOL;
echo 'Name:        ', $group->name, PHP_EOL;
echo 'Short name:  ', $group->shortName ?? '-', PHP_EOL;
echo 'Status:      ', $group->status->value, PHP_EOL;
echo 'Types:       ', implode(', ', $group->types()) ?: '-', PHP_EOL;
echo PHP_EOL;

echo count($group->dimensions), ' dimensions:', PHP_EOL;

foreach ($group->dimensions as $dimension) {
    echo '  ', $dimension->type, ' ', $dimension->code, '  ', $dimension->name ?? '-', PHP_EOL;
}
```

## Run

```sh
php examples/dimension-groups/read-dimension-group/example.php NL-CUSTOMERS
```

```text
Code:        NL-CUSTOMERS
Name:        Dutch customers
Short name:  NL customers
Status:      active
Types:       DEB, KPL

3 dimensions:
  DEB 1000  Example customer
  DEB 1001  Another customer
  KPL 00000  General
```
