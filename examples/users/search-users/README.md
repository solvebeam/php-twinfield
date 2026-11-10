# Search users

Searches users by code with a pattern (`*` matches anything, `?` one character), and compares the default search with `withMutualCompanies()`.

| Method | What it does |
|---|---|
| `search($pattern)` | Pattern on code and name; `inCode()` or `inName()` to match one of them. |
| `limit()`, `offset()`, `get()`, `all()`, `count()` | Paging, one page, all pages, or only the number of users. |
| `withMutualCompanies()` | Finder option `mutualoffices=1`: users with access to at least one of your companies. |
| `withoutAccessRules()` | Finder option `accessrules=0`: also users the access rules hide. Only for users with access to user management. |

The default search can give fewer users than `withMutualCompanies()`, and those extra users can be read with `get()`. So the default search does not always give all users of the organisation.

## Code

```php
$twinfield = Example::twinfield();

$users = $twinfield->users();

$page = $users->search('*_AI')->inCode()->limit(10)->get();

foreach ($page as $user) {
    echo '  ', str_pad($user->code, 16), ' ', $user->name, PHP_EOL;
}

echo 'Default search: ', $users->search()->count(), ' users', PHP_EOL;
echo 'Sharing a company with you: ', $users->search()->withMutualCompanies()->count(), ' users', PHP_EOL;
```

## Run

```sh
php examples/users/search-users/example.php
php examples/users/search-users/example.php 'J*'
```

```text
2 user(s) with a code matching *_AI
  JDOE_AI          John Doe (AI)
  JSMITH_AI        Jane Smith (AI)
Default search: 18 users
Sharing a company with you: 31 users
```
