# Read user

Reads one user with all settings: the code you pass, or else the user of your access token.

`get()` returns a `User`; `find()` returns `null` instead of throwing when the code does not exist.

| Property | What it holds |
|---|---|
| `code`, `name`, `shortName`, `email` | The user; `shortName` is `null` when not set. |
| `status` | `UserStatus`: active, deleted or hidden (a deleted user that is used in transactions). |
| `isCurrentUser` | `true` for the user of the access token. |
| `type`, `culture` | `UserType` (accountant, client of an accountant, regular) and `UserCulture`, the language of Twinfield. |
| `role` | `UserRole`: code, name, short name and level, e.g. `LVL1`. |
| `invitationStatus`, `accountEmail` | Invitation to a Wolters Kluwer account, and its email address (`null` when not invited). |
| `companies` | `CompanyAccess`: `restricted`, the `companies` of a restricted user (code and name), `defaultCompany` and `templateCompanyAccess`. `hasAccessTo()` and `codes()` help. |
| `account` | `UserAccount`: `locked`, `lastLogon`, `failedLogons`, `singleSignOn`, `passwordExpires`, `disabledFrom`, `disabledUntil`, and `isDisabledAt()`. |
| `smsLogin` | Whether the SMS logon package is enabled, and the phone number. |
| `time`, `expenseDefaults` | Time & Expenses: authoriser, week hours, and the default dimensions of the hour journals. |
| `federation` | Login through the identity provider of the organisation; `null` when not enabled. |
| `demo`, `exchangeQuota`, `fileManagerQuota` | Training user (clients of an accountant only), and the Analysis and File Manager quota. |
| `created`, `modified`, `modifiedBy`, `touched` | When and by whom the user was changed, and how often. |

A user either has access to all companies of the organisation (`restricted` is `false`, the list is empty) or only to the listed companies. The documentation calls `restricted` "not in use", but Twinfield returns it like that.

The password is never returned. When Twinfield returns something the SDK does not expect, such as an unknown culture, you get an `UnexpectedResponseException` with the path of the element.

## Code

```php
$twinfield = Example::twinfield();

try {
    $user = $twinfield->users()->get($code);
} catch (UserNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo 'Role:          ', $user->role->code, ' (', $user->role->name, ', level ', $user->role->level, ')', PHP_EOL;
echo 'Last logon:    ', $user->account->lastLogon?->format('Y-m-d H:i') ?? 'never', PHP_EOL;
echo 'Disabled now:  ', $user->account->isDisabledAt(new DateTimeImmutable()) ? 'yes' : 'no', PHP_EOL;
echo 'Companies:     ', $user->companies->restricted ? count($user->companies->companies) . ' companies' : 'all companies', PHP_EOL;
echo 'Access here:   ', $user->hasAccessTo((string) $twinfield->companyCode()) ? 'yes' : 'no', PHP_EOL;
```

## Run

```sh
php examples/users/read-user/example.php
php examples/users/read-user/example.php JDOE
```

```text
Code:          JDOE
Name:          John Doe (John)
Email:         john.doe@example.com
Status:        active
Type:          accountant, nl-NL
Role:          LVL1 (Level 1, level 2)
Invitation:    Active
SSO:           Enabled
Last logon:    2026-11-09 07:24
Disabled now:  no
Companies:     243 companies
               1000 Example Holding B.V.
               1001 Example Trading B.V.
               1002 Example Retail B.V.
Default:       chosen after logging in
Access here:   yes (1000)
Week hours:    0
Changed:       2026-11-03 13:04 by ADMIN
```
