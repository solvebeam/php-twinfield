# Delete supplier

> **This example changes data in Twinfield.** Only run it on a test administration.

Deletes the supplier with the code you pass, or else a new example supplier, and makes it active again.

A supplier that is used in a transaction cannot be deleted: Twinfield hides it instead, and `delete()` returns `DeleteResult::Hidden`. Both deleted and hidden suppliers keep their code and can be made active again with `activate()`.

## Code

```php
$twinfield = Example::twinfield();

$suppliers = $twinfield->suppliers();

// The code from the command line, or else a new example supplier.
$example = !isset($argv[1]);
$code = $argv[1] ?? $suppliers->create(SupplierInput::create('Example supplier to delete'))->code;

$result = $suppliers->delete($code);

echo match ($result) {
    DeleteResult::Deleted => 'Deleted ' . $code,
    // A supplier used in a transaction cannot be deleted: Twinfield hides it.
    DeleteResult::Hidden => 'Hidden ' . $code . ': it is used in a transaction',
}, PHP_EOL;

echo 'Status: ', $suppliers->get($code)->status->value, PHP_EOL;

// Deleted and hidden suppliers can be made active again.
$suppliers->activate($code);

echo 'Status: ', $suppliers->get($code)->status->value, PHP_EOL;

// Clean up the example supplier.
if ($example) {
    $suppliers->delete($code);
}
```

## Run

```sh
php examples/suppliers/delete-supplier/example.php
```

```text
Deleted 2010
Status: deleted
Status: active
```
