# Budget by project

Shows the budget and actual amounts per period, general ledger account, cost center and project.

`byProject()` sends the `GetBudgetByProject` query: the lines are also split by dimension 2 (cost center) and dimension 3 (`$total->dimension3`, the project or fixed asset). Both are `null` for amounts without that dimension.

`BudgetTotals` has helpers to group the lines yourself: `periods()` and `generalLedgerAccounts()` give the distinct values, and `forPeriod()`, `forGeneralLedgerAccount()`, `forDimension2()` and `forDimension3()` filter them.

## Code

```php
$twinfield = Example::twinfield();
$budgets = $twinfield->budgets();

// Budget code, year and optional project from the command line, e.g. `001 2025 P0001`.
$code = $argv[1] ?? iterator_to_array($budgets->search()->all(), false)[0]->code ?? null;
$years = $twinfield->periods()->years();
$year = (int) ($argv[2] ?? ($years === [] ? date('Y') : max($years)));

if ($code === null) {
    echo 'The company has no budgets.', PHP_EOL;

    exit;
}

$query = $budgets->totals($code, $year);

if (isset($argv[3])) {
    $query = $query->dimension3($argv[3]);
}

$totals = $query->byProject();

foreach ($totals->periods() as $period) {
    echo 'Period ', $period, PHP_EOL;

    foreach ($totals->forPeriod($period) as $total) {
        echo sprintf(
            '  %-8s %-10s %-10s budget %12s  actual %12s',
            $total->generalLedgerAccount,
            $total->dimension2 ?? '-',
            $total->dimension3 ?? '-',
            $total->budget->total,
            $total->actual->total,
        ), PHP_EOL;
    }
}
```

## Run

```sh
php examples/budgets/budget-by-project/example.php
php examples/budgets/budget-by-project/example.php 001 2025 P0001
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
Period 1
  4000     KPL100     P0001      budget     500.0000  actual     450.0000
  4000     KPL100     -          budget    1500.0000  actual    1650.5000
Period 2
  4000     KPL100     P0001      budget     500.0000  actual     610.0000
```
