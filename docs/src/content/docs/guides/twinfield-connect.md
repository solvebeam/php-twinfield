---
title: Twinfield Connect
description: Log in to Twinfield without a Twinfield client ID of your own, through the OAuth proxy of SolveBeam.
---

To log in to Twinfield your application needs a client ID from Twinfield, and to get one you have to pass Twinfield's certification. [Twinfield Connect](https://github.com/solvebeam/twinfield-connect) is an OAuth proxy of SolveBeam that lets your application log in through SolveBeam's certified Twinfield client instead.

It is a separate package:

```sh
composer require solvebeam/twinfield-connect
```

## When to use it

- You want to try the SDK, or build something for your own organisation, without requesting a client ID.
- You build an application that others install on their own site, so you do not know the redirect URL in advance.

With your own Twinfield client ID, use the [Twinfield provider](/guides/oauth2/) instead.

## How it works

1. Your application registers once with Twinfield Connect and gets a client ID and secret. These are of Twinfield Connect, not of Twinfield.
2. To log in, you send the user to Twinfield Connect. It shows the name of your application and the host the user returns to, and then sends the user on to Twinfield.
3. After the login the user comes back on your redirect URI with a code, which you exchange for tokens at Twinfield Connect.

The access token is a normal Twinfield access token. API calls go directly to Twinfield, not through Twinfield Connect.

Good to know:

- Twinfield asks the user for permission in the name of SolveBeam, not of your application.
- The refresh token only works with Twinfield Connect. When you switch to your own client ID later, your users have to log in again.
- Refreshing an access token goes through Twinfield Connect, so your application depends on it being reachable.

## Register

Do this once per application or site, not per user. Store the client ID and secret next to your other credentials; the secret is not shown again.

```php
<?php

use SolveBeam\Twinfield\Connect\Registration;

$client = (new Registration())->register(
	'My application',
	['https://example.com/twinfield/callback'],
);

echo $client->clientId, PHP_EOL;
echo $client->clientSecret, PHP_EOL;
```

A redirect URI is an `https` URL, or `http` on `localhost` for development.

## Log in

`TwinfieldConnect` is a [league/oauth2-client](https://oauth2-client.thephpleague.com/) provider, like the Twinfield provider. The difference is PKCE, which Twinfield Connect requires: keep `getPkceCode()` with the state, and give it back with `setPkceCode()` before you exchange the code.

```php
<?php

use SolveBeam\Twinfield\Connect\ConnectClient;
use SolveBeam\Twinfield\Connect\Provider\TwinfieldConnect;
use SolveBeam\Twinfield\OAuth2\Scope;

$provider = TwinfieldConnect::forClient(
	new ConnectClient($storedClientId, $storedClientSecret),
	'https://example.com/twinfield/callback',
);

// 1. Send the user to Twinfield, via Twinfield Connect.
$url = $provider->getAuthorizationUrl([
	'scope' => Scope::values(...Scope::identity()),
	'nonce' => bin2hex(random_bytes(16)),
]);

$_SESSION['twinfield_state'] = $provider->getState();
$_SESSION['twinfield_pkce'] = $provider->getPkceCode();

// 2. On your redirect URI: check the state, then exchange the code.
$provider->setPkceCode($_SESSION['twinfield_pkce']);

$token = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);

// 3. Store the refresh token.
$refreshToken = $token->getRefreshToken();
```

See the [Login example](/examples/getting-started/login/) for the checks around these steps; they are the same.

## Use it with the SDK

`RefreshTokenAccessTokenProvider` accepts the provider in place of the Twinfield provider. The rest of the SDK works the same.

```php
<?php

use SolveBeam\Twinfield\Authentication\RefreshTokenAccessTokenProvider;
use SolveBeam\Twinfield\Twinfield;

$tokens = new RefreshTokenAccessTokenProvider($provider, $refreshToken, $cache);

$twinfield = Twinfield::create($tokens)->company('1000');
```

The scopes are the same too: see [OAuth2 and scopes](/guides/oauth2/#scopes). Twinfield Connect passes them on to Twinfield, so the SolveBeam client must be allowed to request them.
