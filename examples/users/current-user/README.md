# Current user

Shows which Twinfield user the access token belongs to.

`$twinfield->userCode()` gives the code without a request: the access token provider has it from the access token validation (`twf.organisationUserCode`), which it already calls to find the cluster URL. `users()->current()` reads that user, and Twinfield marks it with `isCurrentUser`.

| Access token provider | Where the user code comes from |
|---|---|
| `RefreshTokenAccessTokenProvider` | The access token validation, cached together with the access token. |
| `StaticAccessTokenProvider` | The required `userCode` argument, e.g. with `StaticAccessTokenProvider::fromValidation($token, $validation)`. |

See [Token info](../../getting-started/token-info/) for the access token validation itself.

## Code

```php
$twinfield = Example::twinfield();

echo 'Logged in as:  ', $twinfield->userCode(), PHP_EOL;

$me = $twinfield->users()->current();

echo 'Name:          ', $me->name, ' <', $me->email, '>', PHP_EOL;
echo 'Role:          ', $me->role->name, PHP_EOL;
echo 'Companies:     ', $me->companies->restricted ? implode(', ', $me->companies->codes()) : 'all companies', PHP_EOL;
```

## Run

```sh
php examples/users/current-user/example.php
```

```text
Logged in as:  API
Name:          API user <api@example.com>
Role:          Level 1
Companies:     all companies
```
