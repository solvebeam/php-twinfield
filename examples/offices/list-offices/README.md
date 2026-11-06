# List offices

Shows all offices (administrations) the logged in user has access to.

`offices()->list()` returns a list of `Office` objects with a `code` and a `name`. It fetches all pages for you, so you get every office, also when there are hundreds.

## Code

```php
$twinfield = Example::twinfield();

foreach ($twinfield->offices()->list() as $office) {
    echo $office->code, ' ', $office->name, PHP_EOL;
}
```

Looking for one office? Use `$twinfield->offices()->find('1000')`, which returns an `Office` or `null`.

## Run

```sh
php examples/offices/list-offices/example.php
```

```text
1000 Example Holding B.V.
1001 Example Retail B.V.
1002 Example Services B.V.
```
