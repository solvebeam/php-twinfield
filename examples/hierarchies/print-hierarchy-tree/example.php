<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code of the reporting structure, and `--nodes` to leave out the accounts.
$code = $argv[1] ?? 'TWFRGS32';
$nodesOnly = in_array('--nodes', $argv ?? [], true);

$hierarchy = $twinfield->hierarchies()->get($code);

// RecursiveTreeIterator draws the tree; each line is the prefix plus the node or account as string.
$tree = new RecursiveTreeIterator($nodesOnly ? $hierarchy->nodes() : $hierarchy);

foreach ($tree as $line) {
    echo $line, PHP_EOL;
}
