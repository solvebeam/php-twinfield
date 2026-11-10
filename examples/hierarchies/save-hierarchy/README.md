# Save reporting structure

Creates a reporting structure with nodes, accounts and access rights, or changes the description of an existing one. This example changes data: run it on a test company.

- `HierarchyInput::create($code, $name)` starts a new reporting structure; its root node gets the same code and name. `addNode($node, $parent)` adds a `HierarchyNodeInput` under the root node or under the node with code `$parent`.
- `$hierarchy->edit()` gives an input with everything of an existing reporting structure, with the node IDs and `touched` values. Change it with `changeNode($code, fn ($node) => …)`, `removeNode($code)`, `name()`, `description()` and `accessRights()`.
- `hierarchies()->save($input)` replaces the whole reporting structure and returns it as Twinfield saved it, with the IDs of new nodes.

Twinfield checks `touched`: when someone else saved the reporting structure since you read it, Save fails with `HierarchySaveMessage::Touched`. Errors of nodes are in the same `HierarchyException`, with the node code in `$message->node`.

## Code

```php
$twinfield = Example::twinfield();
$hierarchies = $twinfield->hierarchies();
$code = $argv[1] ?? 'SDK_EXAMPLE';

// Save replaces the whole reporting structure: start from the existing one, so the nodes keep
// their IDs, or create a new one.
try {
    $input = $hierarchies->get($code)->edit()->description('Changed by the SDK on ' . date('Y-m-d H:i'));
} catch (HierarchyNotFoundException) {
    $input = HierarchyInput::create($code, 'SDK example')
        ->addNode(HierarchyNodeInput::create('R', 'Revenue')->accounts(HierarchyAccount::profitAndLoss('8000')))
        ->addNode(HierarchyNodeInput::create('C', 'Costs'))
        ->addNode(HierarchyNodeInput::create('C1', 'Staff')->accounts(HierarchyAccount::profitAndLoss('4000')), 'C')
        ->accessRights(
            HierarchyAccessInput::user($twinfield->userCode(), HierarchyAccessType::Edit),
            HierarchyAccessInput::everyone(HierarchyAccessType::None),
        );
}

try {
    $hierarchy = $hierarchies->save($input);
} catch (HierarchyException $exception) {
    foreach ($exception->messages as $message) {
        echo $message->node ?? $code, ': ', $message->code->value, ' ', $message->text, PHP_EOL;
    }

    exit(1);
}

echo 'Saved ', $hierarchy->code, ' (touched ', $hierarchy->touched, ')', PHP_EOL;

foreach (new RecursiveTreeIterator($hierarchy) as $line) {
    echo $line, PHP_EOL;
}
```

To bind or unbind a few accounts, `bind()` and `unbind()` are simpler: see [Bind accounts](../bind-accounts/).

## Run

```sh
php examples/hierarchies/save-hierarchy/example.php
php examples/hierarchies/save-hierarchy/example.php SDK_EXAMPLE
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.
