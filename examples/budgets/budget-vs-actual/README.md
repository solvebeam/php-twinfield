# Budget vs. actual

Compares the budget with the actual amounts per general ledger account, for periods 1–12 of a year.

Budgets come from the [BudgetService](https://developers.twinfield.com/documentation/api/master-data/budget/), a Command & Query web service that can only read. `budgets()->totals($code, $year)` starts an immutable query; filters such as `periods(1, 12)` return a new query, and `byProfitAndLoss()` sends it.

The result is a `BudgetTotals` list of `BudgetTotal` lines, one per general ledger account and period, each with `budget` and `actual` amounts (`debit`, `credit`, `total`). Twinfield only returns lines with a budget or actual amount.

## Code

```php
$twinfield = Example::twinfield();
$budgets = $twinfield->budgets();

// Budget code and year from the command line; defaults: the first budget and the last year.
$code = $argv[1] ?? iterator_to_array($budgets->search()->all(), false)[0]->code ?? null;
$years = $twinfield->periods()->years();
$year = (int) ($argv[2] ?? ($years === [] ? date('Y') : max($years)));

if ($code === null) {
    echo 'The company has no budgets.', PHP_EOL;

    exit;
}

$totals = $budgets->totals($code, $year)->periods(1, 12)->byProfitAndLoss();

echo 'Budget ', $code, ', ', $year, ', periods 1–12', PHP_EOL, PHP_EOL;
echo sprintf('%-10s %14s %14s %14s', 'Account', 'Budget', 'Actual', 'Difference'), PHP_EOL;

// Amounts are exact decimal strings: add them up with bcmath, not with floats.
foreach ($totals->generalLedgerAccounts() as $account) {
    $budget = '0';
    $actual = '0';

    foreach ($totals->forGeneralLedgerAccount($account) as $total) {
        $budget = bcadd($budget, $total->budget->total, 2);
        $actual = bcadd($actual, $total->actual->total, 2);
    }

    echo sprintf('%-10s %14s %14s %14s', $account, $budget, $actual, bcsub($actual, $budget, 2)), PHP_EOL;
}
```

Amounts are exact decimal strings as Twinfield returns them, such as `2100.5000` or `0`, never floats. This example uses [bcmath](https://www.php.net/bcmath) to add them up; a money library works too. A `total` is debit minus credit: positive for costs, negative for revenue.

Other filters:

- `period(3)` or `periods(1, 6)`: periods, both included. Default: 0 (opening balance) to 55.
- `generalLedgerAccounts('8000', '8999')` or `generalLedgerAccount('8000')`.
- `dimension2('KPL100', 'KPL199')` and `dimension3('P0001')`: cost centers and projects, see [By cost center](../budget-by-cost-center/) and [By project](../budget-by-project/).
- `finalOnly()` or `provisionalOnly()`: actual amounts of final or provisional transactions only. Default: both.

An unknown budget code throws a `BudgetNotFoundException`.

## Run

```sh
php examples/budgets/budget-vs-actual/example.php
php examples/budgets/budget-vs-actual/example.php 001 2025
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
Budget 001, 2025, periods 1–12

Account            Budget         Actual     Difference
4000             24000.00       25210.50        1210.50
4100              6000.00        5450.00        -550.00
8000           -180000.00     -172500.00        7500.00
```
