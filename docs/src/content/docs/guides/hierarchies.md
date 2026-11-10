---
title: Reporting structures
description: Read reporting structures (hierarchies) with their nodes and general ledger accounts, draw them as a tree, and save, bind, unbind, hide and delete them.
---

A reporting structure (rapportagestructuur) groups the general ledger accounts of a company in a tree of nodes, for reports such as a balance sheet, a profit and loss account or RGS (Referentie Grootboekschema). Twinfield calls them hierarchies: the [hierarchies service](https://developers.twinfield.com/documentation/api/miscellaneous/hierarchy/) reads, saves and hides them, and binds accounts to their nodes.

| Twinfield documentation | Resource | Status |
|---|---|---|
| [Hierarchy](https://developers.twinfield.com/documentation/api/miscellaneous/hierarchy/) | `$twinfield->hierarchies()` | Load and the Finder searches verified live (read-only). Save, Bind, Unbind, Hide and Delete are only tested offline |
| [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) `HIE`, `HND` | `search()`, `searchNodes()` | Verified live |

Every request needs a company in the request header: `$twinfield->company('1000')->hierarchies()`. Without one, Twinfield answers with SOAP fault 359; the SDK throws a `LogicException` before sending.

## At a glance

```php
$hierarchies = $twinfield->company('1000')->hierarchies();

foreach ($hierarchies->search()->all() as $summary) {
    echo $summary->code, ' ', $summary->name, PHP_EOL;
}

$hierarchy = $hierarchies->get('TWFRGS32');

foreach (new RecursiveTreeIterator($hierarchy) as $line) {
    echo $line, PHP_EOL;
}
```

```text
\-TWFRGS32 RGS  3.2
  |-B BALANS
  | |-BIVA Immateriële vaste activa
  | | |-BIVAKOU Kosten van oprichting en van uitgifte van aandelen
…
```

## Organisation and company

The reporting structures and their nodes belong to the organisation: every company has the same ones. The general ledger accounts bound to the nodes belong to a company. `get()` gives the accounts of the company of the client (`$hierarchy->company`), and `bind()` and `unbind()` change them:

```php
$nodes = $twinfield->company('1000')->hierarchies()->get('MGMT');      // accounts of 1000
$other = $twinfield->company('1001')->hierarchies()->get('MGMT');      // same nodes, accounts of 1001
```

## Reading a reporting structure

`get($code)` returns a `Hierarchy`:

- `code`, `name`, `description` (null when empty) and `touched`, the number of times it was saved.
- `rootNode`: the top `HierarchyNode`, with the code and name of the reporting structure.
- `accessRights`: a list of `HierarchyAccess` (`type`: `None`, `Use`, `Bind` or `Edit`, and the `userCode`, `roleCode` or `officeCode` it is for). The last one, without codes, is the access of everyone else.

A `HierarchyNode` has an `id` (for `bind()` and `unbind()`), a `code` (unique within the reporting structure), `name`, `description`, `accounts` and `children`. In the reporting structures we saw, a node has either accounts or child nodes, never both.

A `HierarchyAccount` has a `type` (`GeneralLedgerAccountType::BalanceSheet` or `ProfitAndLoss`), a `code` and a `balanceType`: `Balance`, or `Debit` or `Credit` when the node only shows a debit or a credit balance. An account with `Debit` is usually bound to a second node with `Credit`, for example a bank account that can be overdrawn.

```php
$node = $hierarchy->node('B');          // anywhere in the tree, or null

$node->accounts;                        // accounts of the node itself
$node->allAccounts();                   // also those of the nodes below it, in tree order
$hierarchy->allNodes();                 // all nodes, the root node first, in tree order
```

An unknown code gives a `HierarchyNotFoundException`.

## Iterating and drawing the tree

A `Hierarchy` and a `HierarchyNode` are iterable with PHP's recursive iterators:

| Iterator | Gives | Children of a node |
|---|---|---|
| `$hierarchy` (`getIterator()`) | the root node | its accounts, then its child nodes |
| `$hierarchy->nodes()` | the root node | its child nodes |
| `$node` (`getIterator()`) | the accounts and child nodes of the node | the same, recursively |
| `$node->nodes()` | the child nodes of the node | their child nodes |

The first and third are a `HierarchyNodeAccountIterator`, the others a `HierarchyNodeIterator`; both are a `RecursiveIterator`. A node is shown as `code name` (`B BALANS`), an account as `code (type, balance type)` (`1100 (BAS, Balance)`), so [`RecursiveTreeIterator`](https://www.php.net/manual/en/class.recursivetreeiterator.php) draws the tree without more code:

```php
foreach (new RecursiveTreeIterator($hierarchy->nodes()) as $line) {      // nodes only
    echo $line, PHP_EOL;
}

$nodes = new RecursiveIteratorIterator($hierarchy->nodes(), RecursiveIteratorIterator::SELF_FIRST);

foreach ($nodes as $node) {                                             // every node, depth first
    echo str_repeat('  ', $nodes->getDepth()), $node->name, PHP_EOL;
}
```

`RecursiveCallbackFilterIterator` leaves out parts of the tree, for example the nodes without accounts. See [Print reporting structure tree](/examples/hierarchies/print-hierarchy-tree/).

## Searching

`search($pattern)` uses the Finder with type `HIE` and gives `HierarchySummary` objects (`organisation`, `code`, `name`). `searchNodes($hierarchyCode, $pattern)` uses type `HND` and gives `HierarchyNodeSummary` objects (code and name, no ID): all nodes, the root node included, but not in tree order. Use `get()` for the tree.

## Saving

Save replaces the whole reporting structure, with all nodes and access rights. Start from `HierarchyInput::create()` for a new one, or from `$hierarchy->edit()` to change one: the input then keeps the node IDs and `touched` values.

```php
use SolveBeam\Twinfield\Hierarchy\HierarchyAccessInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccessType;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccount;
use SolveBeam\Twinfield\Hierarchy\HierarchyInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyNodeInput;

$hierarchy = $hierarchies->save(
    HierarchyInput::create('MGMT', 'Management report')
        ->addNode(HierarchyNodeInput::create('R', 'Revenue')->accounts(HierarchyAccount::profitAndLoss('8000')))
        ->addNode(HierarchyNodeInput::create('C', 'Costs'))
        ->addNode(HierarchyNodeInput::create('C1', 'Staff')->accounts(HierarchyAccount::profitAndLoss('4000')), parent: 'C')
        ->accessRights(
            HierarchyAccessInput::user('SUPER', HierarchyAccessType::Edit),
            HierarchyAccessInput::everyone(HierarchyAccessType::None),
        ),
);

$hierarchies->save(
    $hierarchy->edit()
        ->changeNode('C1', fn (HierarchyNodeInput $node) => $node->name('Personnel'))
        ->removeNode('R'),
);
```

`save()` returns the reporting structure as Twinfield saved it, with the IDs of the new nodes. Twinfield checks `touched`: when someone else saved the reporting structure since you read it, Save fails with `HierarchySaveMessage::Touched`. Read it again and repeat your change.

The input has no `accessRightTabAccessible`: the SDK sends `true` for an access right of a user, role or company and `false` for the one of everyone else, as in the reporting structures Twinfield returns.

## Binding accounts

To add or remove a few accounts, `bind()` and `unbind()` change one node without saving the whole reporting structure. Pass the node or its ID:

```php
$node = $hierarchies->get('MGMT')->node('C1');

$hierarchies->bind($node, HierarchyAccount::profitAndLoss('4010'), HierarchyAccount::profitAndLoss('4020'));
$hierarchies->unbind($node, HierarchyAccount::profitAndLoss('4020'));
```

The SDK sends the company of the client as `office`, the company of the accounts.

## Hiding and deleting

`hide($code)` hides a reporting structure; `delete($code)` deletes it. Delete is in the WSDL, not in the documentation.

## Errors

Twinfield answers every request with a list of messages. The SDK throws the errors (type `Error`) as a `HierarchyException` with `HierarchyMessage` objects (`type`, `code`, `text`, `parameters` and, for a node, `node`). Each operation has its own codes, all `HierarchyMessageCode` enums:

| Operation | Codes |
|---|---|
| Load, Hide, Delete | `HierarchyLoadMessage`: `InvalidCode` (thrown as `HierarchyNotFoundException`), `UserRoleNotAllowedToPerformThisAction` |
| Save | `HierarchySaveMessage` for the reporting structure, `HierarchyNodeMessage` for a node (e.g. `NodeNotUnique`, `BindingNotUnique`, `MaximumDepthExceeded`), `HierarchyAccessMessage` for an access right |
| Bind | `HierarchyBindMessage`: `BindingConflict`, `BindingNotUnique`, `InvalidNode`, `ReadOnly`, `BindingInvalid` |
| Unbind | `HierarchyUnbindMessage`: `InvalidNode` |

```php
try {
    $hierarchies->save($input);
} catch (HierarchyException $exception) {
    foreach ($exception->messages as $message) {
        echo $message->node ?? '-', ' ', $message->code->value, ': ', $message->text, PHP_EOL;
    }
}
```

The differences between the documentation, the WSDL and the live responses are in [Documentation differences](/guides/documentation-differences/#reporting-structures).

## Examples

- [List reporting structures](/examples/hierarchies/list-hierarchies/)
- [Print reporting structure tree](/examples/hierarchies/print-hierarchy-tree/)
- [Accounts of a node](/examples/hierarchies/node-accounts/)
- [Save reporting structure](/examples/hierarchies/save-hierarchy/)
- [Bind accounts](/examples/hierarchies/bind-accounts/)
- [Delete reporting structure](/examples/hierarchies/delete-hierarchy/)
