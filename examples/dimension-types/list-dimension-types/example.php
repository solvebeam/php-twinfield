<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$types = $twinfield->dimensionTypes();

// A financial level from the command line, e.g. `2`, or else all dimension types.
$search = isset($argv[1]) ? $types->search()->financialLevel((int) $argv[1]) : $types->search();

printf('%-4s  %-20s  %-24s  %s' . PHP_EOL, 'Code', 'Name', 'Mask', 'Levels (financial, time)');

foreach ($search->all() as $summary) {
    $type = $types->get($summary->code);

    printf(
        '%-4s  %-20s  %-24s  %d, %d' . PHP_EOL,
        $type->code,
        $type->name,
        $type->mask,
        $type->levels->financial,
        $type->levels->time,
    );
}
