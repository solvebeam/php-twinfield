# Token info

Shows who an access token belongs to. Twinfield has two endpoints for this:

- **Access token validation** (`getAccessTokenValidation()`): the organisation, the user, the expiry time and the **cluster URL** that API calls go to. `RefreshTokenAccessTokenProvider` uses it to find the cluster for you.
- **User info** (`getResourceOwner()`): the claims of the scopes requested at login, as a `TwinfieldResourceOwner`.

The SDK client reuses the organisation and user code of the validation: `$twinfield->organisation()` and `$twinfield->userCode()` return them without a request, and `$twinfield->users()->current()` reads that user (see [Current user](../../users/current-user/)). Every model the SDK reads carries the organisation, see [Organisations](/guides/organisations/). With a `StaticAccessTokenProvider`, take them from the validation:

```php
$tokens = StaticAccessTokenProvider::fromValidation($accessToken, $validation);
```

## Code

```php
$token = new AccessToken(['access_token' => $accessToken]);

$validation = $provider->getAccessTokenValidation($token);
echo $validation->organisation->code, ' ', $validation->clusterUrl, PHP_EOL;

$user = $provider->getResourceOwner($token);
echo $user->organisationUserCode, ' ', $user->organisationId, PHP_EOL;
```

Which claims you get depends on the scopes:

| Scope | Claims |
|-------|--------|
| `twf.user` | `twf.id` (only for users with a Twinfield ID), `twf.organisationUserCode`, `twf.organisationId` |
| `twf.organisation` | `twf.organisationCode`, `twf.organisationId`, `twf.clusterUrl` |
| `twf.organisationUser` | `twf.organisationUserCode`, `twf.organisationCode`, `twf.organisationId` |

See [OpenID Connect scopes](../../../README.md#scopes).

## Run

```sh
php examples/getting-started/token-info/example.php
```

```text
Access token validation
  Organisation code:  EXAMPLE
  Organisation ID:    0b6a…
  User code:          JDOE
  Cluster URL:        https://api.accounting.twinfield.com
  Expires:            2026-11-06 15:04:05

Twinfield user
  Organisation:       EXAMPLE
  Code:               JDOE
  Name:               John Doe
  Role:               Level 1

User info
  sub:                      9d2e…
  twf.organisationUserCode: JDOE
  twf.organisationId:       0b6a…
  …
```
