# List suppliers

Lists the code and name of all suppliers, or of those matching the pattern you pass, e.g. `"*B.V.*"`.

`search()` returns a `CodeNameSearch`: `all()` fetches page by page while you iterate, `get()` gives one page and `count()` the number of matches. By default the pattern matches code and name; narrow it with `inCode()` or `inName()`, or search suppliers by bank account number (`inBankAccount()`) or address fields such as street and city (`inAddress()`):

```php
$suppliers->search('*Utrecht*')->inAddress()->all();
$suppliers->search('NL91ABNA*')->inBankAccount()->all();
$suppliers->search()->modifiedSince(new DateTimeImmutable('-1 week'))->all();
```

## Code

```php
$twinfield = Example::twinfield();

// All suppliers, or those matching the pattern from the command line, e.g. "*B.V.*".
$search = $twinfield->suppliers()->search($argv[1] ?? '*');

echo 'Suppliers: ', $search->count(), PHP_EOL;

foreach ($search->all() as $supplier) {
    echo $supplier->code, ' ', $supplier->name, PHP_EOL;
}
```

## Run

```sh
php examples/suppliers/list-suppliers/example.php
```

```text
Suppliers: 3
2000 Example Office Supplies B.V.
2001 Sample Wholesale
2002 Demo Logistics B.V.
```
