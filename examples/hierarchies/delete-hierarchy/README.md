# Delete hierarchy

Deletes or hides a reporting structure. This example changes data: run it on a test company, after [Save hierarchy](../save-hierarchy/).

- `hierarchies()->delete($code)` deletes it. Delete is in the WSDL of the hierarchies service, not in its documentation.
- `hierarchies()->hide($code)` hides it (`--hide`).

An unknown code gives a `HierarchyNotFoundException`.

## Code

```php
$twinfield = Example::twinfield();

// The reporting structure of the save-hierarchy example; `--hide` hides it instead.
$code = $argv[1] ?? 'SDK_EXAMPLE';
$hide = in_array('--hide', $argv ?? [], true);

try {
    if ($hide) {
        $twinfield->hierarchies()->hide($code);
    } else {
        $twinfield->hierarchies()->delete($code);
    }
} catch (HierarchyNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo $hide ? 'Hidden: ' : 'Deleted: ', $code, PHP_EOL;
```

## Run

```sh
php examples/hierarchies/delete-hierarchy/example.php
php examples/hierarchies/delete-hierarchy/example.php SDK_EXAMPLE --hide
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.
