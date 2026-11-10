---
title: Declarations
description: List VAT, ICT and tax group returns, download them as XBRL or XML, get the payment reference and set their status.
---

Twinfield prepares the VAT returns (btw-aangifte), ICT returns (opgaaf ICP) and tax group returns (fiscale eenheid) of a company. The [declarations service](https://developers.twinfield.com/documentation/api/miscellaneous/declarations/) lists them, gives them as XBRL or XML, gives the payment reference, and marks them as sent, approved or rejected.

| Twinfield documentation | Resource | Status |
|---|---|---|
| [Declarations](https://developers.twinfield.com/documentation/api/miscellaneous/declarations/) | `$twinfield->declarations()` | All 14 queries, verified live (read-only). The 3 status commands are only tested offline |

Every request needs a company in the request header, also the ones for the whole organisation: `Twinfield::create($accessTokenProvider)->company('1000')`. Without one, Twinfield answers with SOAP fault 359; the SDK throws a `LogicException` before sending.

## At a glance

```php
use SolveBeam\Twinfield\Declaration\DeclarationType;

$declarations = $twinfield->company('1000')->declarations();

foreach ($declarations->list(year: 2026) as $return) {
    echo $return->id, ' ', $return->type->value, ' ', $return->period, ': ', $return->status->step->name, PHP_EOL;

    if ($return->type === DeclarationType::VatTurnover) {
        file_put_contents($return->id . '.xbrl', $declarations->vatReturnXbrl($return->id));
        echo 'Pay with reference ', $declarations->vatReturnPaymentReference($return->id) ?? '-', PHP_EOL;
    }
}
```

## Listing returns

| Method | Twinfield operation |
|---|---|
| `all()` | `GetAllSummaries` |
| `list(offset, limit)` | `GetRangeOfSummaries` |
| `list(offset, limit, modifiedSince: …)` | `GetRangeOfSummariesSinceModified` |
| `list(offset, limit, year: …)` | `GetRangeOfSummariesByDeclarationYear` |
| `list(offset, limit, year: …, modifiedSince: …)` | `GetRangeOfSummariesByDeclarationYearAndSinceModified` |
| `count()` | `GetNumberOfDeclarations` |
| `allForOrganisation()`, `listForOrganisation(…)` | The same, with company code `*` |

Each return is a `DeclarationSummary`:

- `id`: the document ID, to download the return or change its status.
- `type`: a `DeclarationType`: `VatTurnover` (`VATTURNOVER`), `Ict` (`VATICT`), `YearlySupplementation` (`YEARLYSUPPLETION`), `TaxGroup` (`TAXGROUP`) or `TaxGroupYearlySupplementation` (`TXGRPYRSP`).
- `name`, `year` and `period`, e.g. `Btw-aangifte 2026 Kwartaal jan t/m mrt`, `2026` and `Kwartaal jan t/m mrt`. Name and period are in the language of the user; a yearly return has the period `Jaaropgave`.
- `status`: the step (`DeclarationStatusStep`, from `Created` to `Authorized`), a description in the language of the user, and extra information (null in every return seen).
- `assignee`: the user the return is assigned to.
- `company` and `companyName`: the company of the return. With `*` this is not the company of the client.

Returns come by ID, oldest first. `offset` is 0-based: `list(offset: 0, limit: 100)` gives the first hundred. Twinfield has no maximum page size; all 5,828 returns of an organisation came in one response (about 4 MB, formatted).

`modifiedSince` gives the returns created or changed at or after that moment, to the second. The SDK sends it with its time zone (`2026-11-06T11:07:46+01:00`); Twinfield respects it. The summaries have no modification date, so keep your own.

```php
$changed = $declarations->listForOrganisation(limit: 1000, modifiedSince: $lastSync);
```

## Downloading a return

| Type | XBRL | XML | Payment reference |
|---|---|---|---|
| `VatTurnover`, `YearlySupplementation` | `vatReturnXbrl()` | `vatReturnXml()` | `vatReturnPaymentReference()` |
| `Ict` | `ictReturnXbrl()` | `ictReturnXml()` | – |
| `TaxGroup`, `TaxGroupYearlySupplementation` | `taxGroupVatReturnXbrl()` | `taxGroupVatReturnXml()` | `taxGroupVatReturnPaymentReference()` (`TaxGroup` only) |

The XBRL and XML methods return a standalone XML document as a string: `xbrli:xbrl` for XBRL, `AangifteOmzetbelasting` (no namespace) or `OpgaafICP` for XML. Only the document ID counts, not the company of the client.

The payment reference (betalingskenmerk) is e.g. `7222629111601270+`, or null when the return has none: every yearly supplementary return, and the oldest VAT return seen (2010).

## Changing the status

```php
$declarations->setToSent($id);
$declarations->setToApproved($id);
$declarations->setToRejected($id, 'Wrong period');
```

These only change the status in Twinfield; nothing is sent to the tax authorities. The documentation calls the reason for `setToRejected()` mandatory (the WSDL does not), so the SDK requires it. The response of these commands is not verified live yet.

## Errors

- **Messages.** Twinfield gives errors as messages in the response, not as a SOAP fault. The SDK throws a `DeclarationException` with all error messages; each has a `type`, a `code` (`DeclarationLoadMessage` for queries, `DeclarationStatusMessage` for the status commands), a `text` in the language of the user and `parameters`.

  ```php
  try {
      $declarations->vatReturnPaymentReference($ictReturnId);
  } catch (DeclarationException $exception) {
      $exception->messages[0]->code; // DeclarationLoadMessage::InvalidDocumentType
  }
  ```

- **Unknown document ID.** A SOAP fault with error code `100` ("An error occurred on the server"), thrown as `ClassicServiceFaultException`.
- **Unknown company code.** An empty list, not an error.

Where Twinfield differs from its documentation is listed in [Differences with the documentation](/guides/documentation-differences/#declarations).
