# List dimension groups

Lists the code and name of all dimension groups of the company, or those that match a pattern.

`dimensionGroups()->search()` searches them with the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) (type `GRP`). `count()` asks for the number of matches with one small request; `all()` goes through all pages for you. Deleted groups are not in the results.

`dimensionType('DEB')` keeps only the groups that have dimensions of that type, here customers. A group with customers and cost centers is found with both `DEB` and `KPL`.

Each result is a `DimensionGroupSummary` with `code` and `name`. Use `get()` for the dimensions (see [Read dimension group](../read-dimension-group/)). To check one code, use `exists('NL-CUSTOMERS')`.

## Code

```php
$twinfield = Example::twinfield();

// A pattern from the command line, e.g. `NL*`, or else all groups.
$search = $twinfield->dimensionGroups()->search($argv[1] ?? '*');

// Optionally only the groups of one dimension type, e.g. `DEB` for customers.
if (isset($argv[2])) {
    $search = $search->dimensionType($argv[2]);
}

echo $search->count(), ' dimension groups', PHP_EOL;

foreach ($search->all() as $group) {
    echo $group->code, '  ', $group->name, PHP_EOL;
}
```

## Run

```sh
php examples/dimension-groups/list-dimension-groups/example.php
php examples/dimension-groups/list-dimension-groups/example.php '*' DEB
```

```text
2 dimension groups
KEY-ACCOUNTS  Key accounts
NL-CUSTOMERS  Dutch customers
```
