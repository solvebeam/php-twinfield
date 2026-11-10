<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code of the reporting structure and of one of its nodes.
$code = $argv[1] ?? 'TWFRGS32';
$nodeCode = $argv[2] ?? 'B';

$hierarchy = $twinfield->hierarchies()->get($code);
$node = $hierarchy->node($nodeCode);

if ($node === null) {
    echo 'Reporting structure ', $code, ' has no node ', $nodeCode, '.', PHP_EOL;

    exit(1);
}

echo $node, ' (ID ', $node->id, '), company ', $hierarchy->company->code, PHP_EOL;

// The accounts of the node and of all nodes below it.
$accounts = $node->allAccounts();

foreach ($accounts as $account) {
    echo '  ', $account->type->value, ' ', $account->code, ' ', $account->balanceType->value, PHP_EOL;
}

echo count($accounts), ' accounts in ', count($node->allNodes()), ' nodes.', PHP_EOL;
