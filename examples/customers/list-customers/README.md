# List customers

Lists the code and name of all customers, or of those matching the pattern you pass, e.g. `"*B.V.*"`.

`search()` returns a `CodeNameSearch`: `all()` fetches page by page while you iterate, `get()` gives one page and `count()` the number of matches. By default the pattern matches code and name; narrow it with `inCode()` or `inName()`, or search customers by bank account number (`inBankAccount()`) or address fields such as street and city (`inAddress()`):

```php
$customers->search('*Amsterdam*')->inAddress()->all();
$customers->search('NL91ABNA*')->inBankAccount()->all();
$customers->search()->modifiedSince(new DateTimeImmutable('-1 week'))->all();
```

## Code

```php
$twinfield = Example::twinfield();

// All customers, or those matching the pattern from the command line, e.g. "*B.V.*".
$search = $twinfield->customers()->search($argv[1] ?? '*');

echo 'Customers: ', $search->count(), PHP_EOL;

foreach ($search->all() as $customer) {
    echo $customer->code, ' ', $customer->name, PHP_EOL;
}
```

## Run

```sh
php examples/customers/list-customers/example.php
```

```text
Customers: 3
1000 Example Trading B.V.
1001 Sample Retail
1002 Demo Wholesale B.V.
```
