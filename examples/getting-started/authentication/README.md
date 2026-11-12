# Authentication

How to create a Twinfield client in your own application, without the example helper.

Twinfield uses OAuth 2.0. A user logs in once (authorization code flow) and your application stores the **refresh token**. With that refresh token, `RefreshTokenAccessTokenProvider` gets access tokens and refreshes them when they expire (after about an hour). It also finds the cluster URL of the organisation for you.

## Code

```php
use SolveBeam\Twinfield\Authentication\RefreshTokenAccessTokenProvider;
use SolveBeam\Twinfield\OAuth2\Provider\Twinfield as TwinfieldProvider;
use SolveBeam\Twinfield\Twinfield;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$provider = new TwinfieldProvider([
    'clientId' => 'your-client-id',
    'clientSecret' => 'your-client-secret',
    'redirectUri' => 'https://example.com/twinfield/callback',
]);

$tokens = new RefreshTokenAccessTokenProvider(
    $provider,
    refreshToken: $storedRefreshToken,
    cache: new FilesystemAdapter('twinfield'), // any PSR-6 cache
);

$twinfield = Twinfield::create($tokens)->company('1000');
```

`Twinfield::create()` finds an installed PSR-18 HTTP client and PSR-17 factories automatically. You can also pass your own:

```php
$twinfield = Twinfield::create($tokens, $httpClient, $requestFactory, $streamFactory);
```

## Getting the refresh token

A user logs in once with the authorization code flow, and you store the refresh token you get back. See [Login](../login/) for a complete example.

Request at least these scopes: `openid`, `twf.organisationUser` and `offline_access` (needed for a refresh token). See [OpenID Connect scopes](../../../README.md#scopes).

## Storing a new refresh token

Twinfield currently returns the same refresh token on every refresh. If it ever returns a new one, `RefreshTokenAccessTokenProvider` uses it from then on. Store it so you have it on the next request:

```php
if ($tokens->refreshToken() !== $storedRefreshToken) {
    $storedRefreshToken = $tokens->refreshToken(); // save it
}
```

## When the refresh token stops working

A refresh token is valid for about 25 years, but it can stop working earlier. The first API call then throws an `IdentityProviderException`. Ask the user to log in again:

```php
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

try {
    $offices = $twinfield->offices()->list();
} catch (IdentityProviderException $exception) {
    $error = $exception->getResponseBody()['error'] ?? null;

    // Send the user to the login again.
}
```

| Error | Cause |
|-------|-------|
| `invalid_grant` | Wrong client ID or secret; refresh token invalid, expired or issued to another client; client may not use `offline_access` or a requested scope |
| `invalid_request` | Refresh token missing; organisation inactive or deleted; user deleted, disabled, locked or password expired |
| `unsupported_grant_type` | Grant type missing or invalid (a bug, not something the user can fix) |

## Run

```sh
export TWINFIELD_CLIENT_ID=… TWINFIELD_CLIENT_SECRET=… TWINFIELD_REDIRECT_URI=…
export TWINFIELD_REFRESH_TOKEN=… TWINFIELD_COMPANY=…
php examples/getting-started/authentication/example.php
```

After `composer twinfield:setup` you find the refresh token in `~/.config/twinfield/token.json`.

```text
Connected to https://api.accounting.twinfield.com
Company 1000 has access to 3 offices.
```

## What `Example::twinfield()` does

The other examples start with `$twinfield = Example::twinfield();`. That helper does the same as this example, with the values from `~/.config/twinfield/`. The first time, it asks for your client details and opens the login in your browser.
