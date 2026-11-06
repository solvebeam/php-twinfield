# Login

Lets a user log in to Twinfield from your web application and gives you the **refresh token** to store. This is the OAuth 2.0 authorization code flow.

## Flow

1. Send the user to `$provider->getAuthorizationUrl()` with the scopes you need. Keep the random `state` in the session.
2. Twinfield redirects back to your redirect URI with `?code=…&state=…`, or with `?error=…` if the user cancelled or the client is set up wrongly.
3. Check the `state`, then exchange the code for tokens with `getAccessToken('authorization_code', …)`. The code is valid for 5 minutes.
4. Store the refresh token. From then on, use it with `RefreshTokenAccessTokenProvider`: see [Authentication](../authentication/).

## Code

```php
// 1. Login
$url = $provider->getAuthorizationUrl([
    'scope' => ['openid', 'twf.user', 'twf.organisation', 'twf.organisationUser', 'offline_access'],
    'nonce' => bin2hex(random_bytes(16)),
]);
$_SESSION['twinfield_state'] = $provider->getState();
header('Location: ' . $url);

// 2–3. Callback
if (!hash_equals($_SESSION['twinfield_state'] ?? '', $_GET['state'] ?? '')) {
    throw new RuntimeException('State mismatch.');
}

$token = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);

// 4. Store this
$refreshToken = $token->getRefreshToken();
```

`twf.organisationUser` is required to log in, and `offline_access` is needed to get a refresh token. See [OpenID Connect scopes](../../../README.md#scopes).

## Token lifetimes

| Token | Valid for |
|-------|-----------|
| Authorization code | 5 minutes |
| Access token | 1 hour |
| Refresh token | about 25 years, until revoked |

## Run

Register `http://localhost:8765/callback` as redirect URI for your client, then:

```sh
export TWINFIELD_CLIENT_ID=… TWINFIELD_CLIENT_SECRET=… TWINFIELD_REDIRECT_URI=http://localhost:8765/callback
php examples/getting-started/login/example.php
```

This starts PHP's built-in web server. Open http://localhost:8765 and log in. In your own application, the two steps are two routes in your framework.

```text
Logged in to Twinfield.

Organisation:  EXAMPLE
User:          JDOE
Cluster:       https://api.accounting.twinfield.com
Access token:  valid until 2026-11-06 15:04:05
Refresh token: 3f1c…

Store the refresh token and use it with RefreshTokenAccessTokenProvider,
see examples/getting-started/authentication.
```

## Notes

- The `nonce` is sent along as Twinfield documents, but this library does not validate the `id_token`. You only need the access and refresh token for the API.
- Twinfield also supports the implicit flow (`response_type=id_token token`). It is meant for JavaScript and native apps without a back end. A PHP application can keep the client secret safe, so it uses the authorization code flow.
