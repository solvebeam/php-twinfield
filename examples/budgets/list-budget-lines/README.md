# List budget lines

Lists the budgets per year with their balance and their monetary lines that have a value, from the REST API.

- `budgets()->annual()` gives the budgets with the balance per year; `list()` the budgets themselves, with their GUID.
- `lines($budgetId, $year)` searches the lines, with filters on period, type, account, cost center and project. Debit and credit are exact numeric strings (quantities for `qty1` and `qty2` lines).
- Lines refer to accounts by GUID; `ledgerAccounts()->get()` gives the code.

`budgets()->totals()` compares budget and actual amounts with the BudgetService. Needs the scopes `readBudgets` and `readBookkeeping`.

## Code

```php
$twinfield = Example::twinfield();
$budgets = $twinfield->budgets();
$codes = [];

foreach ($budgets->annual()->all() as $annual) {
    echo $annual->budget->code, ' ', $annual->budget->name, ' ', $annual->year, ': balance ', $annual->balance, PHP_EOL;

    $lines = $budgets->lines($annual->budget->id, $annual->year)->type(BudgetLineType::Monetary);

    foreach ($lines->all() as $line) {
        if ($line->debit === '0' && $line->credit === '0') {
            continue;
        }

        $account = $line->key->generalLedgerAccountId;
        $codes[$account] ??= $twinfield->ledgerAccounts()->get($account)->code;

        echo '  ', $line->key->period, ' ', $codes[$account];
        echo ': debit ', $line->debit, ', credit ', $line->credit, PHP_EOL;
    }
}
```

## Run

```sh
php examples/budgets/list-budget-lines/example.php
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
001 Budget 2026 2026: balance -12000.00
  2026/01 8000: debit 0, credit 1000
  2026/02 8000: debit 0, credit 1000
```
