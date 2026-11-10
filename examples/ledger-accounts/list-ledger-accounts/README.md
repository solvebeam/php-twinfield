# List ledger accounts

Lists the balance sheet and profit and loss accounts with their GUID, from the REST API. The GUID is the `uid` of the dimension in the XML web services, and the REST API uses it everywhere (VAT codes, budgets, fixed assets), so this is the way to turn those GUIDs into codes.

- `ledgerAccounts()->search($pattern)` searches code and name; `types()` filters on `LedgerAccountType` (also customers, suppliers, cost centers, projects, …).
- `limit(1)->get()` reads one page with `total` (all accounts) and `filteredTotal` (the matches); `all()` goes through all pages.
- `get($id)` reads one account by GUID.

Needs one of the scopes `readBookkeeping`, `readBudgets`, `readCustomers`, `readFixedAssets`, `readSuppliers`, `readVat` or `readVatGroup`.

## Code

```php
$twinfield = Example::twinfield();

$pattern = $argv[1] ?? '*';

$search = $twinfield->ledgerAccounts()->search($pattern)
    ->types(LedgerAccountType::Balance, LedgerAccountType::ProfitAndLoss)
    ->orderByCode();

$page = $search->limit(1)->get();

echo $page->filteredTotal, ' of ', $page->total, ' ledger accounts match ', $pattern, PHP_EOL;

foreach ($search->all() as $index => $account) {
    echo sprintf(
        '[%d/%d] %s %s (%s, subanalysis %s) %s',
        $index + 1,
        $page->filteredTotal,
        $account->code,
        $account->name,
        $account->type->value,
        $account->subanalysis->value,
        $account->id,
    ), PHP_EOL;
}
```

## Run

```sh
php examples/ledger-accounts/list-ledger-accounts/example.php
php examples/ledger-accounts/list-ledger-accounts/example.php '80*'
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
3 of 2799 ledger accounts match 80*
[1/3] 8000 Revenue high VAT (profitAndLoss, subanalysis allowed) 3f2504e0-4f89-11d3-9a0c-0305e82c3301
[2/3] 8010 Revenue low VAT (profitAndLoss, subanalysis allowed) 6fa459ea-ee8a-3ca4-894e-db77e160355e
[3/3] 8020 Revenue export (profitAndLoss, subanalysis allowed) 16fd2706-8baf-433b-82eb-8c7fada847da
```
