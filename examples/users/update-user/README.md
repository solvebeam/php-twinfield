# Update user

Changes the week hours of a user (by default yourself) and restores them.

Start from `$user->edit()`: it has the current settings, so `update()` keeps everything you do not change. Left out of `edit()`, so they stay as they are:

- the password (never returned) and the invitation status (locked by Twinfield);
- `demo`, for users that are no client of an accountant;
- the password expiry, for users with single sign-on;
- the company list, for users with access to all companies. Sending a list would restrict them.

For a user restricted to some companies, `grantAccessTo()` and `revokeAccessTo()` change the list. On a user with access to all companies they throw a `LogicException`, so you never take away access by accident.

## Code

```php
$twinfield = Example::twinfield();

$users = $twinfield->users();
$user = $users->get($twinfield->userCode());

$updated = $users->update($user->edit()->weekHours(32));

// For a restricted user: $users->update($user->edit()->grantAccessTo('1001'));

$users->update($user->edit());
```

## Run

```sh
php examples/users/update-user/example.php
php examples/users/update-user/example.php JDOE
```

Illustrative output (not yet run live):

```text
Week hours before: not set
Week hours after:  32
Restored:          not set
```
