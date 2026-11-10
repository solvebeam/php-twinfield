# List reporting structures

Shows the code and name of all reporting structures (rapportagestructuren), or of the ones that match a pattern.

Reporting structures come from the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) with type `HIE`. They belong to the organisation: every company gives the same list. `hierarchies()->search()` returns a `CodeNameSearch` with `HierarchySummary` objects (`organisation`, `code`, `name`). For the nodes and accounts, use [Print reporting structure tree](../print-hierarchy-tree/).

## Code

```php
$twinfield = Example::twinfield();

// Optional search pattern from the command line, e.g. `RGS*`.
$pattern = $argv[1] ?? '*';

foreach ($twinfield->hierarchies()->search($pattern)->all() as $hierarchy) {
    echo $hierarchy->code, '  ', $hierarchy->name, PHP_EOL;
}
```

Search the nodes of one reporting structure with `hierarchies()->searchNodes('TWFRGS32', 'BIVA*')` (Finder type `HND`). The nodes are not in tree order and have no ID.

## Run

```sh
php examples/hierarchies/list-hierarchies/example.php
php examples/hierarchies/list-hierarchies/example.php "RGS*"
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
TWFRGS32  RGS  3.2
MGMT  Management report
```
