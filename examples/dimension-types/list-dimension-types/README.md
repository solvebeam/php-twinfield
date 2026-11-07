# List dimension types

Lists the dimension types of the company with their code mask and levels, or only those of one financial level.

`dimensionTypes()->search()` searches them with the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) (type `DMT`). Each result is a `DimensionTypeSummary` with `code` and `name`; `get()` reads the whole type (see [Read dimension type](../read-dimension-type/)).

The search can be narrowed with two dimension type options:

- `financialLevel(2)`: only the types of that financial level, e.g. `CRD`, `DEB` and `KPL`.
- `ofDimension('1000')`: only the types that have a dimension with that code, e.g. `BAS` and `DEB`.

This example reads every type, one request each. Twinfield limits the number of requests per second; on HTTP 429 you get a `RateLimitExceededException` (see [Error handling](../../getting-started/error-handling/)).

## Code

```php
$twinfield = Example::twinfield();
$types = $twinfield->dimensionTypes();

// A financial level from the command line, e.g. `2`, or else all dimension types.
$search = isset($argv[1]) ? $types->search()->financialLevel((int) $argv[1]) : $types->search();

printf('%-4s  %-20s  %-24s  %s' . PHP_EOL, 'Code', 'Name', 'Mask', 'Levels (financial, time)');

foreach ($search->all() as $summary) {
    $type = $types->get($summary->code);

    printf(
        '%-4s  %-20s  %-24s  %d, %d' . PHP_EOL,
        $type->code,
        $type->name,
        $type->mask,
        $type->levels->financial,
        $type->levels->time,
    );
}
```

## Run

```sh
php examples/dimension-types/list-dimension-types/example.php
php examples/dimension-types/list-dimension-types/example.php 2
```

```text
Code  Name                  Mask                      Levels (financial, time)
ACT   Activiteiten          A[0-9][0-9][0-9]          4, 2
AST   Vaste activa          6[0-9][0-9][0-9][0-9]     3, 0
BAS   Balans                *                         1, 0
CRD   Crediteuren           2[0-9][0-9][0-9]          2, 0
DEB   Debiteuren            1[0-9][0-9][0-9]          2, 0
KPL   Kostenplaatsen        *                         2, 0
PNL   Winst & verlies       *                         1, 0
PRJ   Projecten             P[0-9][0-9][0-9][0-9]     3, 1
```
