# List general ledger accounts

Lists profit and loss accounts (`pnl`, default) or balance sheet accounts (`bas`), optionally only the codes matching a pattern such as `"8*"`. It shows the number of accounts and the first 10, hidden ones included.

The two types are two resources: `$twinfield->balanceSheetAccounts()` (`BAS`) and `$twinfield->profitAndLossAccounts()` (`PNL`). `search()` returns a `CodeNameSearch`: `all()` fetches page by page while you iterate, `get()` gives one page and `count()` the number of matches. By default the pattern matches code and name; narrow it with `inCode()` or `inName()`. `includeHidden()` adds hidden accounts, with `$summary->hidden`:

```php
$twinfield->profitAndLossAccounts()->search('8*')->inCode()->all();
$twinfield->balanceSheetAccounts()->search()->group('LIQUID*')->all();
$twinfield->profitAndLossAccounts()->search()->modifiedSince(new DateTimeImmutable('-1 week'))->all();
```

## Code

```php
$twinfield = Example::twinfield();

// "bas" or "pnl" from the command line (default pnl), and an optional pattern, e.g. "8*".
$accounts = ($argv[1] ?? 'pnl') === 'bas' ? $twinfield->balanceSheetAccounts() : $twinfield->profitAndLossAccounts();

$search = $accounts->search($argv[2] ?? '*')->inCode()->includeHidden();

echo ucfirst($accounts->type()->label()), 's: ', $search->count(), PHP_EOL;

foreach ($search->limit(10)->get() as $account) {
    echo $account->code, ' ', $account->name, $account->hidden ? ' (hidden)' : '', PHP_EOL;
}
```

## Run

```sh
php examples/general-ledger-accounts/list-general-ledger-accounts/example.php pnl
```

```text
Profit and loss accounts: 1025
40000 Brutolonen en salarissen
40005 Provisies en tantièmes
40010 Vakantietoeslag
40015 Vakantiebonnen
40020 Brutolonen en salarissen 5
40025 Belaste vergoedingen
40030 Bijzondere beloningen
40035 Dotatie reserve vakantietoeslag
40036 Dotatie reserve vakantiedagen
40040 Brutolonen en salarissen 8
```

```sh
php examples/general-ledger-accounts/list-general-ledger-accounts/example.php bas "01*"
```

```text
Balance sheet accounts: 53
0100 Oprichtingskosten (hidden)
01000 Aanschaffingswaarde kosten van oprichting en uitgifte van aandelen
01001 Cum. afschr. kst. van oprichting en uitgifte van aandelen
…
```
