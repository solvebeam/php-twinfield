---
title: OAuth2 and scopes
description: Log in with the Twinfield OAuth2 provider and request the right OpenID Connect scopes.
---

The SDK includes a [league/oauth2-client](https://oauth2-client.thephpleague.com/) provider for Twinfield.

## Usage

```php
<?php

use SolveBeam\Twinfield\OAuth2\Provider\Twinfield;

$provider = new Twinfield([
	'clientId' => 'your-client-id',
	'clientSecret' => 'your-client-secret',
	'redirectUri' => 'https://example.com/callback',
]);

$authorizationUrl = $provider->getAuthorizationUrl();

// Redirect the user to $authorizationUrl, then handle the callback.

$accessToken = $provider->getAccessToken('authorization_code', [
	'code' => $_GET['code'],
]);

$resourceOwner = $provider->getResourceOwner($accessToken);
$profile = $resourceOwner->toArray();
```

## Scopes

Twinfield supports the following OAuth 2.0 scopes:

| Scope | Description | Claims |
|-------|-------------|--------|
| `openid` | Standard OpenID Connect scope. Required when using identity-related scopes. | - |
| `twf.user` | Contains Twinfield-specific information about the user (identity token).<br><br>**Note:** When adding this scope, add `openid` as well. | • `twf.id` - GUID of Twinfield ID<br>• `twf.organisationUserCode` - User code inside organisation<br>• `twf.organisationId` - GUID of organisation |
| `twf.organisation` | Contains organisation information. | • `twf.organisationCode` - Code of organisation<br>• `twf.organisationId` - GUID of organisation<br>• `twf.clusterUrl` - URL of cluster on which organisation is located |
| `twf.organisationUser` | Contains information about organisation user.<br><br>**⚠️ Important:** This scope is **mandatory** in order to login. | • `twf.organisationUserCode` - Code of the organisation user (used for login)<br>• `twf.organisationCode` - Code of the organisation<br>• `twf.organisationId` - GUID of organisation |
| `readFixedAssets` | Grants read access to Fixed Assets API endpoints. | - |
| `readVat` | Grants read access to VAT Codes API endpoints. | - |
| `offline_access` | Required to receive refresh tokens in the authorization code flow. | - |

The SDK needs `twf.organisationUser` and `twf.organisation`: `getAccessTokenValidation()` throws an `UnexpectedResponseException` when one of their claims is missing. The validation gives the cluster URL and the user the token belongs to; `$twinfield->userCode()` returns that user code without a request, and `$twinfield->users()->current()` reads the user. See [Current user](/examples/users/current-user/).

## Example with scopes

```php
<?php

use SolveBeam\Twinfield\OAuth2\Provider\Twinfield;

$provider = new Twinfield([
	'clientId' => 'your-client-id',
	'clientSecret' => 'your-client-secret',
	'redirectUri' => 'https://example.com/callback',
]);

// Request authorization with specific scopes
$authorizationUrl = $provider->getAuthorizationUrl([
	'scope' => ['openid', 'twf.user', 'twf.organisation', 'twf.organisationUser', 'offline_access']
]);

// Redirect the user to $authorizationUrl, then handle the callback.

$accessToken = $provider->getAccessToken('authorization_code', [
	'code' => $_GET['code'],
]);
```

For more details, see the [Twinfield OpenID Connect documentation](https://developers.twinfield.com/documentation/api/authentication/openid-connect-auth).

For a complete login flow, see the [Login example](/examples/getting-started/login/).
