# Create user

Creates a user with the same type and role as you, restricted to the company of the client, and invites the user to a Wolters Kluwer account.

**A new user can cost money**: Twinfield charges per user, depending on the subscription and the role. That is why the example needs the code and email address on the command line. Changing the role of a user to one that costs more needs `acceptExtraCost()`.

`create()` reads first, so an existing user is never overwritten (`UserAlreadyExistsException`). `save()` creates or updates without that check. Both read the user back.

| Method | Element |
|---|---|
| `email()`, `type()`, `culture()`, `role()` | `email`, `type`, `culture`, `role` |
| `companies()`, `defaultCompany()`, `templateCompanyAccess()` | `office/offices`, `office/default`, `office/templatecompanyaccess` |
| `invite()` | `invitationstatus` = `Invited`; needs `culture()` and `role()`, and only works for organisations Twinfield enabled for invitations. |
| `password()`, `singleSignOn()`, `passwordExpires()`, `disable()` | `password`, `account/sso`, `account/expire`, `account/disable` |
| `smsLogin()`, `timeAuthoriser()`, `weekHours()`, `expenseDefaults()`, `federation()` | `smslogin`, `time`, `expenses`, `federation` |

Only what you set is sent. Inconsistent settings throw before anything is sent, e.g. `invite()` without a role, or a password expiry date with single sign-on.

## Code

```php
$twinfield = Example::twinfield();

$me = $twinfield->users()->current();

$user = $twinfield->users()->create(
    UserInput::create($code, 'Example user')
        ->email($email)
        ->type($me->type)
        ->culture(UserCulture::English)
        ->role($me->role)
        ->companies((string) $twinfield->companyCode())
        ->defaultCompany($twinfield->companyCode())
        ->weekHours(40)
        ->invite(),
);
```

## Run

```sh
php examples/users/create-user/example.php EXAMPLE example.user@example.com
```

Illustrative output (not yet run live):

```text
Created EXAMPLE (example.user@example.com), invitation: Invited
Companies: 1000
```
