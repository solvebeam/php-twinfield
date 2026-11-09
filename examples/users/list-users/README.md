# List users

Lists the code and name of all users of the organisation.

`list()` fetches all Finder pages and returns `UserSummary` objects (code and name). Use `get()` for all settings of a user.

Users belong to the organisation, not to a company, but Twinfield refuses user requests without a company (error 359). Any company works, so `Example::twinfield()` (a client with your default company) is enough.

## Code

```php
$twinfield = Example::twinfield();

foreach ($twinfield->users()->list() as $user) {
    echo str_pad($user->code, 16), ' ', $user->name, PHP_EOL;
}
```

## Run

```sh
php examples/users/list-users/example.php
```

```text
ADMIN            Example Admin
API              API user
JDOE             John Doe
JDOE_AI          John Doe (AI)
JSMITH           Jane Smith
```
