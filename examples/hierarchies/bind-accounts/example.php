<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$hierarchies = $twinfield->hierarchies();

// A reporting structure from the save-hierarchy example, one of its nodes and an account.
$code = $argv[1] ?? 'SDK_EXAMPLE';
$nodeCode = $argv[2] ?? 'C1';
$account = HierarchyAccount::profitAndLoss($argv[3] ?? '4010');

$node = $hierarchies->get($code)->node($nodeCode)
    ?? throw new RuntimeException(sprintf('Reporting structure %s has no node %s.', $code, $nodeCode));

// Binding changes only this node, for the accounts of the company of the client.
$hierarchies->bind($node, $account);

echo 'Bound ', $account, ' to ', $hierarchies->get($code)->node($nodeCode), PHP_EOL;

$hierarchies->unbind($node, $account);

echo 'Unbound it again: ', implode(', ', $hierarchies->get($code)->node($nodeCode)->accounts ?? []), PHP_EOL;
