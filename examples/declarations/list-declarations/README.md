# List declarations

Lists the VAT, ICT and tax group returns of the company for a year (this year by default, or the year you pass), and when the company has none, the first returns of every company of the organisation.

Returns come from the declarations service (`declarations.asmx`). Every request needs a company in the header, so the client needs a company (`company('1000')`); `Example::twinfield()` uses your default company.

- `declarations()->count()`: the number of returns of the company, in all years.
- `declarations()->list(limit: 1000, year: 2026)`: a page of the company's returns as `DeclarationSummary` objects, by ID. `offset` is 0-based. Pass `modifiedSince` for the returns created or changed since then.
- `declarations()->listForOrganisation(...)`: the same for every company of the organisation the user has access to (Twinfield's `*`). `$return->company` tells which company a return belongs to.

The period and the status description are in the language of the user, e.g. `Kwartaal jan t/m mrt` and `Goedgekeurd`. Use `$return->type` (`DeclarationType`) and `$return->status->step` (`DeclarationStatusStep`) in code.

## Code

```php
$twinfield = Example::twinfield();

$declarations = $twinfield->declarations();
$year = (int) ($argv[1] ?? date('Y'));

echo 'Company ', $twinfield->companyCode(), ': ', $declarations->count(), ' returns in all years', PHP_EOL;

$returns = $declarations->list(limit: 1000, year: $year);

if ($returns === []) {
    echo 'No returns in ', $year, '; the returns of every company of the organisation:', PHP_EOL;

    $returns = $declarations->listForOrganisation(limit: 10, year: $year);
}

foreach ($returns as $return) {
    echo sprintf(
        '%6d  %-6s %-16s %-22s %s',
        $return->id,
        $return->company->code,
        $return->type->value,
        $return->period,
        $return->status->step->name,
    ), PHP_EOL;
}

```

An unknown company code gives an empty list, not an error.

## Run

```sh
php examples/declarations/list-declarations/example.php
php examples/declarations/list-declarations/example.php 2025
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
Company 1000: 9 returns in all years
 10812  1000   VATTURNOVER      Kwartaal jan t/m mrt   Approved
 11257  1000   VATICT           Kwartaal jan t/m mrt   Approved
 11871  1000   VATTURNOVER      Kwartaal apr t/m jun   Approved
 11942  1000   VATTURNOVER      Kwartaal jul t/m sep   Created
```
