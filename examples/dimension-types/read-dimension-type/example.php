<?php

declare(strict_types=1);

use SolveBeam\Twinfield\DimensionType\DimensionTypeNotFoundException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

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
