# Error handling

Shows how to catch Twinfield errors and act on them without depending on the message, as the [Twinfield guidelines](https://developers.twinfield.com/documentation/api/types/guidelines) recommend.

Twinfield reports errors in two ways:

- **SOAP faults**: the SDK throws a `SoapFaultException`. Its properties hold the fault detail:
  - `detailCode`: e.g. `354` (classic services) or `YearNotFound` (Command & Query services). Use this to act on an error.
  - `detailMessage`: readable text, in the language of the user. Only for logging or showing to the user.
  - `source`: the called method (classic services only).
  - `detailType`: the fault type, e.g. `PeriodServiceFault` (Command & Query services only).
- **Authorization errors**: HTTP 401 or 403. Without a SOAP fault in the response (Twinfield sends an HTML page for an invalid access token), the SDK throws an `AccessDeniedException`; the HTTP status is the exception code.

Too many requests give a `RateLimitExceededException` with `retryAfterSeconds`.

Match the codes with the enums: `ErrorCode` for the general codes of the classic services, `PeriodServiceFaultCode` for the PeriodService.

## Code

```php
$twinfield = Example::twinfield();

// 1. A Command & Query service with its own fault codes: a year that does not exist.
try {
    $twinfield->periods()->list(1901);
} catch (SoapFaultException $exception) {
    echo 'Type:    ', $exception->detailType, PHP_EOL;
    echo 'Code:    ', $exception->detailCode, PHP_EOL;
    echo 'Message: ', $exception->detailMessage, PHP_EOL;

    if (PeriodServiceFaultCode::tryFrom($exception->detailCode ?? '') === PeriodServiceFaultCode::YearNotFound) {
        echo '→ The year does not exist.', PHP_EOL;
    }
}

echo PHP_EOL;

// 2. A classic service with an invalid access token.
$invalid = Twinfield::create(new StaticAccessTokenProvider('invalid-token'));

try {
    $invalid->offices()->list();
} catch (SoapFaultException $exception) {
    echo 'Code:    ', $exception->detailCode, PHP_EOL;
    echo 'Source:  ', $exception->source, PHP_EOL;
    echo 'Message: ', $exception->detailMessage, PHP_EOL;

    if (ErrorCode::tryFrom($exception->detailCode ?? '') === ErrorCode::TokenInvalid) {
        echo '→ Get a new access token, or let the user log in again.', PHP_EOL;
    }
} catch (AccessDeniedException $exception) {
    echo 'HTTP ', $exception->getCode(), ': ', $exception->getMessage(), PHP_EOL;
}
```

## Error codes

The general error codes of the classic services, as `ErrorCode` cases:

| Code | `ErrorCode` | Meaning |
|---|---|---|
| 100 | `UnexpectedException` | Unexpected exception. |
| 101 | `AuthenticationMissing` | Access token and company ID (or obsolete session ID) are missing. |
| 102 | `AccessDenied` | Access denied. |
| 103 | `CredentialsNoLongerValid` | Log-on credentials are no longer valid. |
| 104 | `LogonDeleted` | Log-on is deleted. |
| 105 | `LogonDisabled` | Log-on is disabled. |
| 106 | `OrganisationInactive` | Organisation is no longer active. |
| 107 | `SmsFailed` | SMS could not be sent. |
| 108 | `ClusterInvalid` | Cluster is invalid, access not allowed. |
| 109 | `NoAccessToCompany` | No access to at least one company. |
| 110 | `LoginNotAllowedOnServer` | Login not allowed on this server. |
| 122 | `ClientSoftwareNotAllowed` | Client software not allowed. |
| 168 | `LoggedOff` | Logged off because another user logged on with the same user name. |
| 186 | `InvalidZlibData` | Invalid Zlib data. |
| 187 | `InvalidXmlData` | Invalid XML data. |
| 351 | `OrganisationUserCodeClaimMissing` | The `twf.organisationUserCode` claim is missing from the token; check the scopes. |
| 352 | `OrganisationCodeClaimMissing` | The `twf.organisationCode` claim is missing from the token; check the scopes. |
| 353 | `OrganisationIdClaimMissing` | The `twf.organisationId` claim is missing from the token; check the scopes. |
| 354 | `TokenInvalid` | Token is invalid. |
| 358 | `TokenNotValidated` | Token could not be validated. |
| 359 | `CompanyIdMissing` | Company ID is missing from the request header. |
| 360 | `AccessTokenAndSessionId` | Access token and session ID are both in the header. |
| 361 | `CompanyIdAndCompanyCode` | Company ID and company code are both in the header. |
| 362 | `CompanyNotSupported` | Company is not supported. |
| 383 | `PaymentFormatNotSupported` | Payment format is not supported. |
| 384 | `PaymentFormatNotSupported384` | Payment format is not supported. |

The SDK sends the access token and company code in the header (never a session ID or company ID), so 360 and 361 should not occur.

## Run

```sh
php examples/getting-started/error-handling/example.php
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
Type:    PeriodServiceFault
Code:    YearNotFound
Message: Year 1901 bestaat niet.
→ The year does not exist.

HTTP 401: Twinfield denied access (HTTP 401 Unauthorized).
```
