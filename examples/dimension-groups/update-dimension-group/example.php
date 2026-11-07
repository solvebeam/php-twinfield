<?php

declare(strict_types=1);

use SolveBeam\Twinfield\DimensionGroup\DimensionGroupInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$groups = $twinfield->dimensionGroups();
$code = Example::unusedDimensionGroupCode($twinfield);
[$first, $second, $third] = Example::customerCodes($twinfield, 3);

// A temporary group with two customers.
$groups->create(DimensionGroupInput::create($code, 'Example key accounts')->add('DEB', $first, $second));

try {
    $group = $groups->get($code);

    echo 'Before: ', $group->name, ' [', implode(', ', $group->codes()), ']', PHP_EOL;

    // edit() has all current values and dimensions: only the changes differ.
    $group = $groups->update(
        $group->edit()
            ->name('Example key accounts 2026')
            ->add('DEB', $third)
            ->remove('DEB', $first),
    );

    echo 'After:  ', $group->name, ' [', implode(', ', $group->codes()), ']', PHP_EOL;
} finally {
    // Clean up.
    $groups->delete($code);
}
