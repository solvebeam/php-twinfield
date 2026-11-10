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
| `offline_access` | Required to receive refresh tokens in the authorization code flow. | - |
| `readArticles` | REST API: read sales articles. | - |
| `writeArticles` | REST API: create, update, delete and import sales articles (also reads them). | - |
| `readBankAccounts` | REST API: PSD2 bank feeds. | - |
| `readBookkeeping` | REST API: ledger accounts. | - |
| `readBookkeepingMatches` | REST API: matching audit trail. | - |
| `readBudgets` | REST API: budget lines, annual budgets and budget periods. | - |
| `writeBudgets` | REST API: change, copy, spread and import budget lines (also reads them). | - |
| `readBudgetScenarios` | REST API: budgets and spreading templates. | - |
| `writeBudgetScenarios` | REST API: create, update and delete budgets and spreading templates (also reads them). | - |
| `readCustomers` | REST API: changed customers, posting rules and ledger accounts. | - |
| `readSuppliers` | REST API: changed suppliers, posting rules and ledger accounts. | - |
| `readFixedAssets` | REST API: fixed assets of the new fixed assets module, with classes, groups and reports. | - |
| `readVat` | REST API: VAT codes. | - |
| `readVatGroup` | REST API: VAT groups; also gives access to the ledger accounts. | - |

Every operation of the [REST API](/guides/rest-api/#scopes) needs one of the `read…`/`write…` scopes on top of the identity scopes; without it Twinfield answers HTTP 403. The `Scope` enum has them all, and `Scope::rest()` lists the REST API scopes. Your OAuth client must be allowed to request them. `AccessTokenValidation::$scopes` has the scopes of a token.

The SDK needs `twf.organisationUser` and `twf.organisation`: `getAccessTokenValidation()` throws an `UnexpectedResponseException` when one of their claims is missing. The validation gives the cluster URL, the organisation and the user the token belongs to; `$twinfield->organisation()` and `$twinfield->userCode()` return them without a request, and `$twinfield->users()->current()` reads the user. See [Current user](/examples/users/current-user/) and [Organisations and companies](/guides/organisations/).

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
