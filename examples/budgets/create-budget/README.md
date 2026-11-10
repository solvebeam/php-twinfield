# Create budget

> **This example changes data in Twinfield.** It deletes the budget again, but only run it on a test administration.

Creates a budget, spreads 12000 over the periods of the last year, changes one period and deletes the budget again (REST API).

- `budgets()->create(BudgetInput::create($code, $name))` creates the budget and reads it back with its GUID.
- `spread()` divides an amount over the periods, evenly or with a spreading template (`spreadingTemplates()`).
- `updateLine()` sets the debit and credit of one line, identified by a `BudgetLineKey` (period, unit of measure, account and optionally cost center and project).
- `delete()` deletes the budget with its lines.

Needs the scopes `writeBudgets`, `writeBudgetScenarios` and `readBookkeeping`. Not verified live yet: see TODO.md.

## Code

```php
$twinfield = Example::twinfield();
$budgets = $twinfield->budgets();

$years = $twinfield->periods()->years();
$year = $years === [] ? (int) date('Y') : max($years);
$account = $twinfield->ledgerAccounts()->search()->types(LedgerAccountType::ProfitAndLoss)->limit(1)->get()->items[0];

$budget = $budgets->create(BudgetInput::create('EX' . random_int(100, 999), 'Example budget ' . $year));

echo 'Created budget ', $budget->code, ' ', $budget->name, PHP_EOL;

try {
    // 12000 over the periods of the year, evenly.
    $budgets->spread($budget->id, $year, UnitOfMeasure::Monetary, $account->id, '12000', SpreadFor::Credit);

    // And one period by hand.
    $key = new BudgetLineKey(Period::of($year, 12), UnitOfMeasure::Monetary, $account->id);
    $budgets->updateLine($budget->id, $key, '0', '2500');

    foreach ($budgets->lines($budget->id, $year)->all() as $line) {
        echo $line->key->period, ' ', $account->code, ': credit ', $line->credit, PHP_EOL;
    }
} finally {
    $budgets->delete($budget->id);

    echo 'Deleted budget ', $budget->code, PHP_EOL;
}
```

## Run

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/budgets/create-budget/example.php
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
Created budget EX512 Example budget 2026
2026/01 8000: credit 1000
2026/02 8000: credit 1000
2026/12 8000: credit 2500
Deleted budget EX512
```
