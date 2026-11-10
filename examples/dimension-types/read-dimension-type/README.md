# Read dimension type

Reads one dimension type: the code from the command line, or else customers (`DEB`). With a second argument, it checks whether that code matches the mask.

`get()` takes a code or a `DimensionTypeCode` (e.g. `DimensionTypeCode::AccountsReceivable`), and returns a `DimensionType`, or throws a `DimensionTypeNotFoundException` when the type does not exist. Use `find()` to get `null` instead.

A dimension type has:

- `name` and `shortName` (`null` when not set; read-only).
- `mask`: a `CodeMask` that the codes of its dimensions must match, e.g. `1[0-9][0-9][0-9]` or `*` for any code. `matches($code)` checks a code, e.g. before creating a customer.
- `levels`: the `financial` level (1 general ledger, 2 e.g. customers, 3 projects, 4 activities), the level in `time` & expenses and in `fixedAssets` (read-only).
- `addressLabels`: the labels of the free address fields `field1`–`field6` of customers and suppliers; `null` when a field is not used.

Twinfield has no read request for dimension types: the SDK sends a `<dimensiontype>` with only the company and the code, which returns the type without changing it.

## Code

```php
$twinfield = Example::twinfield();

// The type from the command line, or else customers (DEB). Optionally a code to check.
$code = $argv[1] ?? 'DEB';
$newCode = $argv[2] ?? null;

try {
    $type = $twinfield->dimensionTypes()->get($code);
} catch (DimensionTypeNotFoundException $exception) {
    echo $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Code:        ', $type->code, PHP_EOL;
echo 'Name:        ', $type->name, PHP_EOL;
echo 'Short name:  ', $type->shortName ?? '-', PHP_EOL;
echo 'Mask:        ', $type->mask, $type->mask->allowsAnyCode() ? ' (any code)' : '', PHP_EOL;
echo 'Levels:      financial ', $type->levels->financial, ', time ', $type->levels->time,
    ', fixed assets ', $type->levels->fixedAssets, PHP_EOL;
echo PHP_EOL;

echo 'Address labels:', PHP_EOL;

foreach ($type->addressLabels->toArray() as $field => $label) {
    echo '  field', $field, '  ', $label ?? '-', PHP_EOL;
}

if ($newCode !== null) {
    echo PHP_EOL, 'Code ', $newCode, ($type->mask->matches($newCode) ? ' matches' : ' does not match'), ' the mask.',
        PHP_EOL;
}
```

## Run

```sh
php examples/dimension-types/read-dimension-type/example.php DEB 1010
```

```text
Code:        DEB
Name:        Debiteuren
Short name:  Debiteuren
Mask:        1[0-9][0-9][0-9]
Levels:      financial 2, time 0, fixed assets 0

Address labels:
  field1  Tav
  field2  Adres
  field3  Postadres
  field4  BTW nummer
  field5  KVK nummer
  field6  -

Code 1010 matches the mask.
```
