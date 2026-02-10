# PHP Twinfield SDK

Modern PHP SDK for the Twinfield API, built on PSR standards.

## PSR Compliance

### PSR-1 (Basic Coding Standard)

The codebase follows PSR-1 fundamentals for PHP files and class structure.

### PSR-2 (Coding Style Guide)

PSR-2 is superseded by PSR-12; this project follows PSR-12.

### PSR-4 (Autoloading)

The SDK uses PSR-4 autoloading for the `SolveBeam\Twinfield\` namespace, configured in `composer.json`.

### PSR-6 (Caching)

Examples use PSR-6 caching for token storage via `symfony/cache`.

- https://www.php-fig.org/psr/psr-6/
- https://github.com/php-fig/cache
- https://packagist.org/packages/psr/cache
- https://github.com/symfony/cache

### PSR-7 (HTTP Messages)

The OAuth2 provider relies on PSR-7 request/response interfaces for HTTP messages.

### PSR-18 (HTTP Client)

The library can work with PSR-18 HTTP clients. Suggested implementations are listed in `composer.json` (e.g., `symfony/http-client`, `php-http/curl-client`) along with a PSR-7 implementation such as `nyholm/psr7`.

### PSR-12 (Coding Style)

Coding style follows PSR-12, enforced via the PHPCS ruleset in `phpcs.xml.dist`.

## OAuth2 Usage

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

## OpenID Connect Authentication

### Scopes

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

### Example with Scopes

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

## Links

- https://chatgpt.com/c/6983bca8-83d0-8327-aafb-c4cb3a774cb6
- https://oauth2-client.thephpleague.com/providers/league/
- https://oauth2-client.thephpleague.com/providers/thirdparty/
- https://github.com/php-fig/cache
- https://github.com/phpro/soap-client
