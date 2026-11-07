# Budget by cost center

Shows the budget and actual amounts per general ledger account, cost center and period, with final transactions only.

`byCostCenter()` sends the `GetBudgetByCostCenter` query: the lines are also split by dimension 2 (`$total->dimension2`). On profit and loss accounts dimension 2 is the cost center; on balance sheet accounts it is the customer or supplier. It is `null` for amounts without a dimension 2.

## Code

```php
$twinfield = Example::twinfield();
$budgets = $twinfield->budgets();

// Budget code, year and optional cost center range from the command line, e.g. `001 2025 KPL100 KPL199`.
$code = $argv[1] ?? iterator_to_array($budgets->search()->all(), false)[0]->code ?? null;
$years = $twinfield->periods()->years();
$year = (int) ($argv[2] ?? ($years === [] ? date('Y') : max($years)));

if ($code === null) {
    echo 'The company has no budgets.', PHP_EOL;

    exit;
}

$query = $budgets->totals($code, $year)->finalOnly();

if (isset($argv[3])) {
    $query = $query->dimension2($argv[3], $argv[4] ?? null);
}

foreach ($query->byCostCenter() as $total) {
    echo sprintf(
        '%2d  %-8s %-10s budget %12s  actual %12s',
        $total->period,
        $total->generalLedgerAccount,
        $total->dimension2 ?? '-',
        $total->budget->total,
        $total->actual->total,
    ), PHP_EOL;
}
```

`dimension2('KPL100', 'KPL199')` limits the lines to a range of cost centers; `dimension2('KPL100')` to one. Filter the result afterwards with `$totals->forDimension2('KPL100')`.

## Run

```sh
php examples/budgets/budget-by-cost-center/example.php
php examples/budgets/budget-by-cost-center/example.php 001 2025 KPL100 KPL199
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
 1  4000     KPL100     budget    2000.0000  actual    2100.5000
 1  4000     KPL200     budget    1500.0000  actual    1320.0000
 1  8000     -          budget  -15000.0000  actual  -12500.0000
 2  4000     KPL100     budget    2000.0000  actual    1980.0000
```
