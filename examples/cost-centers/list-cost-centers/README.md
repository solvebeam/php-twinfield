# List cost centers

Lists the code and name of all cost centers of the company, or those that match a pattern.

Cost centers are dimensions of type `KPL`: the second dimension (`dim2`) of transaction lines on profit and loss accounts. `costCenters()->search()` searches them with the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/). `count()` asks for the number of matches with one small request; `all()` goes through all pages for you.

Each result is a `CostCenterSummary` with `code` and `name`. Use `get()` for all fields (see [Read cost center](../read-cost-center/)).

The search is immutable and can be refined further, e.g. `search('1*')->inCode()`, `->inName()`, `->modifiedSince($date)` or `->limit(10)->get()` for one page.

## Code

```php
$twinfield = Example::twinfield();

// A pattern from the command line, e.g. `1*`, or else all cost centers.
$search = $twinfield->costCenters()->search($argv[1] ?? '*');

echo $search->count(), ' cost centers', PHP_EOL;

foreach ($search->all() as $costCenter) {
    echo $costCenter->code, '  ', $costCenter->name, PHP_EOL;
}
```

## Run

```sh
php examples/cost-centers/list-cost-centers/example.php
```

```text
3 cost centers
100  Sales
200  Marketing
300  Research & development
```
