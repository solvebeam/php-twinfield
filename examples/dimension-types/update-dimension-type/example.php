<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

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
