# Download VAT return

Finds the last VAT return of last year, saves it as XBRL and shows its payment reference (betalingskenmerk).

- `declarations()->vatReturnXbrl($id)`: the return as an XBRL document (a string), as sent to the tax authorities. `vatReturnXml($id)` gives Twinfield's older XML format.
- `declarations()->vatReturnPaymentReference($id)`: e.g. `7222629111601270+`, or null when the return has none, as for every yearly supplementary return.

Use the methods that fit the kind of return (`$return->type`):

| Type | XBRL | XML | Payment reference |
|---|---|---|---|
| `VatTurnover`, `YearlySupplementation` | `vatReturnXbrl()` | `vatReturnXml()` | `vatReturnPaymentReference()` |
| `Ict` | `ictReturnXbrl()` | `ictReturnXml()` | – |
| `TaxGroup`, `TaxGroupYearlySupplementation` | `taxGroupVatReturnXbrl()` | `taxGroupVatReturnXml()` | `taxGroupVatReturnPaymentReference()` (`TaxGroup` only) |

A payment reference of a return that has none by its type gives a `DeclarationException` with `InvalidDocumentType`; an unknown document ID gives a `ClassicServiceFaultException` with error code `100`.

## Code

```php
$twinfield = Example::twinfield();

$declarations = $twinfield->declarations();

// The last VAT return of last year, of this company or else of the organisation.
$year = (int) date('Y') - 1;
$isVatReturn = static fn (DeclarationSummary $return): bool => $return->type === DeclarationType::VatTurnover;
$returns = array_filter($declarations->list(limit: 1000, year: $year), $isVatReturn)
    ?: array_filter($declarations->listForOrganisation(limit: 10000, year: $year), $isVatReturn);

if ($returns === []) {
    echo 'No VAT returns in ', $year, '.', PHP_EOL;

    exit;
}

$return = end($returns);

echo $return->name, ' of company ', $return->company->code, ' (', $return->status->description, ')', PHP_EOL;

$file = sprintf('%s/vat-return-%d.xbrl', sys_get_temp_dir(), $return->id);
file_put_contents($file, $declarations->vatReturnXbrl($return->id));

echo 'XBRL saved to ', $file, ' (', filesize($file), ' bytes)', PHP_EOL;
echo 'Payment reference: ', $declarations->vatReturnPaymentReference($return->id) ?? 'none', PHP_EOL;
```

## Run

```sh
php examples/declarations/download-vat-return/example.php
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
Btw-aangifte 2025 Kwartaal okt t/m dec of company 1000 (Goedgekeurd)
XBRL saved to /tmp/vat-return-11627.xbrl (1907 bytes)
Payment reference: 1234567891501300+
```
