---
title: OAuth2 and scopes
description: Log in with the Twinfield OAuth2 provider and request the right OpenID Connect scopes.
---

Logging in goes through [solvebeam/oauth2-twinfield](https://github.com/solvebeam/twinfield-oauth2-php), a [league/oauth2-client](https://oauth2-client.thephpleague.com/) provider for Twinfield. It is installed with the SDK and also works on its own.

You need a client ID and secret from Twinfield for it. Without them, log in through [Twinfield Connect](/guides/twinfield-connect/).

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

The OpenID Connect scopes identify the user and keep the session; every API needs them:

| Scope | Description | Claims |
|-------|-------------|--------|
| `openid` | Standard OpenID Connect scope. Required when using identity-related scopes. | - |
| `twf.user` | Contains Twinfield-specific information about the user (identity token).<br><br>**Note:** When adding this scope, add `openid` as well. | • `twf.id` - GUID of Twinfield ID<br>• `twf.organisationUserCode` - User code inside organisation<br>• `twf.organisationId` - GUID of organisation |
| `twf.organisation` | Contains organisation information. | • `twf.organisationCode` - Code of organisation<br>• `twf.organisationId` - GUID of organisation<br>• `twf.clusterUrl` - URL of cluster on which organisation is located |
| `twf.organisationUser` | Contains information about organisation user.<br><br>**⚠️ Important:** This scope is **mandatory** in order to login. | • `twf.organisationUserCode` - Code of the organisation user (used for login)<br>• `twf.organisationCode` - Code of the organisation<br>• `twf.organisationId` - GUID of organisation |
| `offline_access` | Required to receive refresh tokens in the authorization code flow. | - |

The SDK needs `twf.organisationUser` and `twf.organisation`: `getAccessTokenValidation()` throws a `SolveBeam\Twinfield\OAuth2\UnexpectedResponseException` when one of their claims is missing. The validation gives the cluster URL, the organisation and the user the token belongs to; `$twinfield->organisation()` and `$twinfield->userCode()` return them without a request, and `$twinfield->users()->current()` reads the user. See [Current user](/examples/users/current-user/) and [Organisations and companies](/guides/organisations/).

### REST API scopes

Every operation of the [REST API](/guides/rest-api/#scopes) needs one of the `read…`/`write…` scopes on top of the identity scopes; without it Twinfield answers HTTP 403. The `Scope` enum has them all, and `Scope::rest()` lists the REST API scopes. Your OAuth client must be allowed to request them. `AccessTokenValidation::$scopes` has the scopes of a token.

| Scope | Description | SDK |
|-------|-------------|-----|
| `readArticles` | Read sales articles. | `salesArticles()`: search, read, cultures, export |
| `writeArticles` | Create, update, delete and import sales articles (also reads them). | `salesArticles()`: create, update, delete, import |
| `readBankAccounts` | PSD2 bank feeds. | `bankFeeds()->psd2()` |
| `readBookkeeping` | Ledger accounts. | `ledgerAccounts()` |
| `readBookkeepingMatches` | Matching audit trail. | `matching()->auditTrail()`, `auditTrailSince()` |
| `readBudgets` | Budget lines, annual budgets and budget periods. | `budgets()`: lines, annual, periods, export |
| `writeBudgets` | Change, copy, spread and import budget lines (also reads them). | `budgets()`: line methods, copy, spread, import |
| `readBudgetScenarios` | Budgets and spreading templates. | `budgets()->list()`, `getById()`, `spreadingTemplates()` |
| `writeBudgetScenarios` | Create, update and delete budgets and spreading templates (also reads them). | `budgets()->create()`, `update()`, `delete()`, `spreadingTemplates()` |
| `readCustomers` | Changed customers, posting rules and ledger accounts. | `customers()->changes()`, `postingRules()` |
| `readSuppliers` | Changed suppliers, posting rules and ledger accounts. | `suppliers()->changes()`, `postingRules()` |
| `readFixedAssets` | Fixed assets of the new fixed assets module, with classes, groups and reports. | `fixedAssets()` |
| `readVat` | VAT codes. | `vatCodes()->searchDetails()`, `getDetails()` |
| `readVatGroup` | VAT groups; also gives access to the ledger accounts. | `ledgerAccounts()` |

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
