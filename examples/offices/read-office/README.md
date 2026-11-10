# Read office

Shows the settings of one office (company): general settings, VAT, system dimensions and intercompany relations.

`offices()->get('1000')` reads an office and `offices()->current()` reads the office of the client. `get()` throws an `OfficeNotFoundException` for an unknown code or an office the user has no access to; `find()` returns null instead. Offices are read-only in the API.

The `Office` object has a section per part of the settings:

- `general`: currencies, type, registration numbers (`vatNumber`, `cocNumber`), last closed year, default bank book and the `address`.
- `systemDimensions`: the dimensions for system purposes, such as the control accounts `accountsReceivable` and `accountsPayable`. Each one is null when not set.
- `systemDimensionTypes`: the dimension type codes for customers (`DEB`), suppliers (`CRD`), balance sheet (`BAS`), and so on.
- `creditManagement`: when open invoices show up in the credit management lists.
- `vatManagement`: the contacts and periods of the VAT declarations and the tax group (fiscal unity).
- `fixedAssets`, `intercompany` and `regional`.
- `lockedFields`: the settings Twinfield does not allow to change now, e.g. the VAT period once declarations were filed. `isLocked(OfficeField::DeclarationTimeframe)` checks one setting.

Optional settings are null when Twinfield returns them empty; unexpected values throw an `UnexpectedResponseException`.

## Code

```php
$twinfield = Example::twinfield();

try {
    // The code from the command line, or else the office of the client.
    $office = isset($argv[1]) ? $twinfield->offices()->get($argv[1]) : $twinfield->offices()->current();
} catch (OfficeNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$general = $office->general;
$vat = $office->vatManagement;
$dimensions = $office->systemDimensions;

echo 'Office:          ', $office->code, ' ', $office->name, PHP_EOL;
echo 'Type:            ', $general->type->name, $general->demo ? ' (demo)' : '', PHP_EOL;
echo 'Currency:        ', $general->baseCurrency->code, PHP_EOL;
echo 'VAT number:      ', $general->vatNumber ?? '-', PHP_EOL;
echo 'CoC number:      ', $general->cocNumber ?? '-', PHP_EOL;
echo 'Address:         ', $general->address->field1 ?? '-', ', ', $general->address->city ?? '-', PHP_EOL;
echo 'Last closed:     ', $general->lastClosedYear ?? '-', PHP_EOL;
echo 'Tax group:       ', $vat->taxGroup?->code ?? '-', PHP_EOL;
echo 'VAT period:      ', $vat->declarationTimeframe, ', starting quarter ', $vat->startingQuarter, PHP_EOL;
echo 'Receivable:      ', $dimensions->accountsReceivable?->code ?? '-', ' ',
    $dimensions->accountsReceivable?->name, PHP_EOL;
echo 'Payable:         ', $dimensions->accountsPayable?->code ?? '-', ' ', $dimensions->accountsPayable?->name, PHP_EOL;
echo 'Customers:       dimension type ', $office->systemDimensionTypes->accountsReceivable->code, PHP_EOL;
echo 'Intercompany:    ', $office->intercompany->status->name, PHP_EOL;

foreach ($office->intercompany->outgoing as $relation) {
    echo '  to ', $relation->office->code, ' on ', $relation->dimension1?->code ?? '-', PHP_EOL;
}

foreach ($office->intercompany->incoming as $relation) {
    echo '  from ', $relation->office->code, ' on ', $relation->dimension1?->code ?? '-', PHP_EOL;
}

echo 'Locked:          ',
    implode(', ', array_map(static fn ($field) => $field->name, $office->lockedFields)) ?: '-', PHP_EOL;
echo 'Modified:        ', $office->modified->format('Y-m-d H:i'), ' by ', $office->user->code, PHP_EOL;
```

## Run

```sh
php examples/offices/read-office/example.php
php examples/offices/read-office/example.php 1000
```

```text
Office:          1000 Example Holding B.V.
Type:            AccountantInternal
Currency:        EUR
VAT number:      NL001234567B01
CoC number:      12345678
Address:         Main street 1, Utrecht
Last closed:     2025
Tax group:       013
VAT period:      1, starting quarter 21
Receivable:      1300 Trade debtors
Payable:         1600 Trade creditors
Customers:       dimension type DEB
Intercompany:    Both
  to 1001 on 1320
  from 1001 on 1640
Locked:          DeclarationTimeframe, StartingQuarter
Modified:        2026-02-03 14:38 by JANE
```
