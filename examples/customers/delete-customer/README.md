# Delete customer

> **This example changes data in Twinfield.** Only run it on a test administration.

Deletes the customer with the code you pass, or else a new example customer, and makes it active again.

A customer that is used in a transaction cannot be deleted: Twinfield hides it instead, and `delete()` returns `DeleteResult::Hidden`. Both deleted and hidden customers keep their code and can be made active again with `activate()`.

## Code

```php
$twinfield = Example::twinfield();

$customers = $twinfield->customers();

// The code from the command line, or else a new example customer.
$example = !isset($argv[1]);
$code = $argv[1] ?? $customers->create(CustomerInput::create('Example customer to delete'))->code;

$result = $customers->delete($code);

echo match ($result) {
    DeleteResult::Deleted => 'Deleted ' . $code,
    // A customer used in a transaction cannot be deleted: Twinfield hides it.
    DeleteResult::Hidden => 'Hidden ' . $code . ': it is used in a transaction',
}, PHP_EOL;

echo 'Status: ', $customers->get($code)->status->value, PHP_EOL;

// Deleted and hidden customers can be made active again.
$customers->activate($code);

echo 'Status: ', $customers->get($code)->status->value, PHP_EOL;

// Clean up the example customer.
if ($example) {
    $customers->delete($code);
}
```

## Run

```sh
php examples/customers/delete-customer/example.php
```

```text
Deleted 1006
Status: deleted
Status: active
```
