# Delete user

Deletes a user with a reason. When the user is used in transactions, Twinfield hides it instead; `activate()` makes a deleted or hidden user active again.

The example needs the code on the command line and refuses to delete the user of your access token.

| Reason | Code |
|---|---|
| `UserDeleteReason::Product` | `product` |
| `UserDeleteReason::ServiceNew` | `servicenew` |
| `UserDeleteReason::PricingNew` | `pricingnew` |
| `UserDeleteReason::SubscriptionChange` | `changetwfsubscription` |
| `UserDeleteReason::Defaulting` | `defaulting` |
| `UserDeleteReason::NoLongerOperative` | `nolongeroperative` |

The reason and comment (at most 1000 characters) are optional; a comment needs a reason.

## Code

```php
$twinfield = Example::twinfield();

$result = $twinfield->users()->delete($code, UserDeleteReason::NoLongerOperative, 'Left the company');

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;
```

## Run

```sh
php examples/users/delete-user/example.php EXAMPLE
```

Illustrative output (not yet run live):

```text
Deleted EXAMPLE.
Status now: deleted
```
