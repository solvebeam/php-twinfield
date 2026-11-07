# Update dimension type

Changes the label of an address field of a dimension type: by default field 6 of customers (`DEB`) becomes `IBAN`. **This changes data.**

Twinfield can change the `name` (40 characters), the `mask` and the six address labels (20 characters each) of a dimension type. The short name and the levels are read-only, and dimension types cannot be created or deleted.

Start from `$type->edit()`: it has all current values, so the update sends them again and only what you change differs. `DimensionTypeInput` is immutable:

```php
$types->update(
    $type->edit()
        ->name('Klanten')
        ->mask('1[0-9][0-9][0-9][0-9]')
        ->addressLabel(6, 'IBAN')
        ->addressLabel(1, null),     // remove a label
);
```

`update()` returns the saved `DimensionType`. A value that is too long throws an `InvalidArgumentException` before anything is sent; other refusals are a `ProcessXmlException` with the messages per element.

## Code

```php
$twinfield = Example::twinfield();
$types = $twinfield->dimensionTypes();

// Type, address field (1–6) and label from the command line; an empty label removes it.
$code = $argv[1] ?? 'DEB';
$field = (int) ($argv[2] ?? 6);
$label = $argv[3] ?? 'IBAN';

$type = $types->get($code);

echo 'Label of field', $field, ' was: ', $type->addressLabels->forField($field) ?? '-', PHP_EOL;

// edit() starts from all current values, so only the label changes.
$updated = $types->update($type->edit()->addressLabel($field, $label));

echo 'Label of field', $field, ' is:  ', $updated->addressLabels->forField($field) ?? '-', PHP_EOL;
```

## Run

```sh
php examples/dimension-types/update-dimension-type/example.php DEB 6 IBAN
```

```text
Label of field6 was: -
Label of field6 is:  IBAN
```
