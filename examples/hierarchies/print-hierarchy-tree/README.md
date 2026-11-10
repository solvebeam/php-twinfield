# Print reporting structure tree

Reads a reporting structure and draws it as an ASCII tree with PHP's [`RecursiveTreeIterator`](https://www.php.net/manual/en/class.recursivetreeiterator.php).

- `hierarchies()->get($code)` returns a `Hierarchy` with all nodes, and the general ledger accounts bound to them in the company of the client. The nodes are the same in every company, the accounts differ.
- Iterating a `Hierarchy` gives the root node and, recursively, the accounts and child nodes of every node (a `HierarchyNodeAccountIterator`). `nodes()` gives the nodes only (a `HierarchyNodeIterator`).
- `RecursiveTreeIterator` turns every node and account into a line: the prefix (`|-`, `\-`, `| `) plus the object as string, `1 Activa` for a node and `1100 (BAS, Balance)` for an account.

`TWFRGS32` is the Twinfield reporting structure for RGS 3.2 (Referentie Grootboekschema); pass the code of another one, for instance from [List reporting structures](../list-hierarchies/).

## Code

```php
$twinfield = Example::twinfield();

// The code of the reporting structure, and `--nodes` to leave out the accounts.
$code = $argv[1] ?? 'TWFRGS32';
$nodesOnly = in_array('--nodes', $argv ?? [], true);

$hierarchy = $twinfield->hierarchies()->get($code);

// RecursiveTreeIterator draws the tree; each line is the prefix plus the node or account as string.
$tree = new RecursiveTreeIterator($nodesOnly ? $hierarchy->nodes() : $hierarchy);

foreach ($tree as $line) {
    echo $line, PHP_EOL;
}
```

Draw one node with `new RecursiveTreeIterator($hierarchy->node('B'))`. To walk the tree in code, use `RecursiveIteratorIterator` (with `SELF_FIRST` for the nodes too), or `$hierarchy->allNodes()` and `$node->allAccounts()`. Leave out nodes with `RecursiveCallbackFilterIterator`, e.g. only the nodes with accounts:

```php
$filter = new RecursiveCallbackFilterIterator(
    $hierarchy->getIterator(),
    static fn ($item) => !$item instanceof HierarchyNode || $item->allAccounts() !== [],
);

foreach (new RecursiveTreeIterator($filter) as $line) {
    echo $line, PHP_EOL;
}
```

## Run

```sh
php examples/hierarchies/print-hierarchy-tree/example.php TWFRGS32
php examples/hierarchies/print-hierarchy-tree/example.php TWFRGS32 --nodes
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
\-TWFRGS32 RGS  3.2
  |-B BALANS
  | |-BIVA Immateriële vaste activa
  | | |-BIVAKOU Kosten van oprichting en van uitgifte van aandelen
  | | | |-BIVAKOUVVP Verkrijgings- of vervaardigingsprijs
  | | | | |-BIVAKOUVVPBEG Beginbalans (overname eindsaldo vorig jaar)
  | | | | |-BIVAKOUVVPINV Investeringen
…
```
