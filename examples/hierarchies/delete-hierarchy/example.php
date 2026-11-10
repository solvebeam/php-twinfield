<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Hierarchy\HierarchyNotFoundException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The reporting structure of the save-hierarchy example; `--hide` hides it instead.
$code = $argv[1] ?? 'SDK_EXAMPLE';
$hide = in_array('--hide', $argv ?? [], true);

try {
    if ($hide) {
        $twinfield->hierarchies()->hide($code);
    } else {
        $twinfield->hierarchies()->delete($code);
    }
} catch (HierarchyNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo $hide ? 'Hidden: ' : 'Deleted: ', $code, PHP_EOL;
