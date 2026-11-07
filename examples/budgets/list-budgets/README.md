# List budgets

Shows the code and name of all budgets of the company, or of the budgets that match a pattern.

Budgets come from the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) with type `BDS`. `budgets()->search()` returns a `CodeNameSearch` with `BudgetSummary` objects (`code`, `name`). For the amounts of a budget, use [Budget vs. actual](../budget-vs-actual/).

## Code

```php
$twinfield = Example::twinfield();

// Optional search pattern from the command line, e.g. `2025*`.
$pattern = $argv[1] ?? '*';

foreach ($twinfield->budgets()->search($pattern)->all() as $budget) {
    echo $budget->code, '  ', $budget->name, PHP_EOL;
}
```

Search in names only with `->inName()`, or get one page with `->limit(10)->get()`.

## Run

```sh
php examples/budgets/list-budgets/example.php
php examples/budgets/list-budgets/example.php "2025*"
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
001  Budget 2025
002  Forecast 2025
```
