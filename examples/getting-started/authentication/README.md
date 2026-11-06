# Authentication

How to create a Twinfield client in your own application, without the example helper.

Twinfield uses OAuth 2.0. A user logs in once (authorization code flow) and your application stores the **refresh token**. With that refresh token, `RefreshTokenAccessTokenProvider` gets access tokens and refreshes them when they expire (after about an hour). It also finds the cluster URL of the organisation for you.

## Code

```php
use SolveBeam\Twinfield\OAuth2\Provider\Twinfield as TwinfieldProvider;
use SolveBeam\Twinfield\OAuth2\RefreshTokenAccessTokenProvider;
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

Send the user to `$provider->getAuthorizationUrl(['scope' => [...]])`, and in your callback exchange the code:

```php
$token = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
$refreshToken = $token->getRefreshToken(); // store this
```

Request at least these scopes: `openid`, `twf.organisationUser` and `offline_access` (needed for a refresh token). See [OpenID Connect scopes](../../../README.md#scopes).

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
