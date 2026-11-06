# Error handling

Shows how to catch Twinfield errors and act on them without depending on the message, as the [Twinfield guidelines](https://developers.twinfield.com/documentation/api/types/guidelines) recommend.

Twinfield reports errors in two ways:

- **SOAP faults**: the SDK throws a `SoapFaultException`. The classic services and the Command & Query services describe the fault differently, so each has its own subclass:
  - `ClassicServiceFaultException` (classic services, `.asmx`): `errorCode` is a general code such as `354`, see `ErrorCode`. `source` holds the called method, when Twinfield sends it.
  - `CommandQueryFaultException` (Command & Query services, `.svc`): `type` is the fault type, e.g. `PeriodServiceFault`, and `errorCode` a code within that type, e.g. `YearNotFound`. For the PeriodService, see `PeriodServiceFaultCode`.

  Use `errorCode` to act on an error. `errorMessage` is readable text in the language of the user, only for logging or showing to the user. A fault without such details is a plain `SoapFaultException`.
- **Authorization errors**: HTTP 401 or 403. Without a SOAP fault in the response (Twinfield sends an HTML page for an invalid access token), the SDK throws an `AccessDeniedException`; the HTTP status is the exception code.

Too many requests give a `RateLimitExceededException` with `retryAfterSeconds`.

## Code

```php
$twinfield = Example::twinfield();

// 1. A Command & Query service with its own fault codes: a year that does not exist.
try {
    $twinfield->periods()->list(1901);
} catch (CommandQueryFaultException $exception) {
    echo 'Type:    ', $exception->type, PHP_EOL;
    echo 'Code:    ', $exception->errorCode, PHP_EOL;
    echo 'Message: ', $exception->errorMessage, PHP_EOL;

    if (PeriodServiceFaultCode::tryFrom($exception->errorCode) === PeriodServiceFaultCode::YearNotFound) {
        echo '→ The year does not exist.', PHP_EOL;
    }
}

echo PHP_EOL;

// 2. A classic service with an invalid access token.
$invalid = Twinfield::create(new StaticAccessTokenProvider('invalid-token'));

try {
    $invalid->offices()->list();
} catch (ClassicServiceFaultException $exception) {
    echo 'Code:    ', $exception->errorCode, PHP_EOL;
    echo 'Source:  ', $exception->source, PHP_EOL;
    echo 'Message: ', $exception->errorMessage, PHP_EOL;

    if (ErrorCode::tryFrom($exception->errorCode) === ErrorCode::TokenInvalid) {
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
