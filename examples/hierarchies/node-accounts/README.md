# Accounts of a node

Shows the general ledger accounts bound to a node of a reporting structure and to all nodes below it, for example to add up their balances.

- `$hierarchy->node($code)` finds a node anywhere in the tree (the root node included), or gives null.
- `$node->accounts` are the accounts bound to the node itself; `$node->allAccounts()` also those of the nodes below it, in tree order.
- An account has a type (`BAS` balance sheet, `PNL` profit and loss), a code and a balance type: `Balance`, or `Debit` or `Credit` when the node only shows a debit or credit balance. Such an account is usually bound twice, once with `Debit` and once with `Credit`, e.g. a bank account that can be overdrawn.

The accounts are those of the company of the client: run it with another company and the same nodes have other accounts. A reporting structure that no one bound accounts to, like `TWFRGS32` often, gives no accounts.

## Code

```php
$twinfield = Example::twinfield();

// The code of the reporting structure and of one of its nodes.
$code = $argv[1] ?? 'TWFRGS32';
$nodeCode = $argv[2] ?? 'B';

$hierarchy = $twinfield->hierarchies()->get($code);
$node = $hierarchy->node($nodeCode);

if ($node === null) {
    echo 'Reporting structure ', $code, ' has no node ', $nodeCode, '.', PHP_EOL;

    exit(1);
}

echo $node, ' (ID ', $node->id, '), company ', $hierarchy->company->code, PHP_EOL;

// The accounts of the node and of all nodes below it.
$accounts = $node->allAccounts();

foreach ($accounts as $account) {
    echo '  ', $account->type->value, ' ', $account->code, ' ', $account->balanceType->value, PHP_EOL;
}

echo count($accounts), ' accounts in ', count($node->allNodes()), ' nodes.', PHP_EOL;
```

## Run

```sh
php examples/hierarchies/node-accounts/example.php TWFRGS32 B
php examples/hierarchies/node-accounts/example.php MGMT 1
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
1 Activa (ID 85241), company 1000
  BAS 0100 Balance
  BAS 0110 Balance
  BAS 1100 Balance
  BAS 1110 Debit
  BAS 1110 Credit
5 accounts in 4 nodes.
```
