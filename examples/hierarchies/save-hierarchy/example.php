<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccessInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccessType;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccount;
use SolveBeam\Twinfield\Hierarchy\HierarchyException;
use SolveBeam\Twinfield\Hierarchy\HierarchyInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyNodeInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyNotFoundException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$hierarchies = $twinfield->hierarchies();
$code = $argv[1] ?? 'SDK_EXAMPLE';

// Save replaces the whole reporting structure: start from the existing one, so the nodes keep
// their IDs, or create a new one.
try {
    $input = $hierarchies->get($code)->edit()->description('Changed by the SDK on ' . date('Y-m-d H:i'));
} catch (HierarchyNotFoundException) {
    $input = HierarchyInput::create($code, 'SDK example')
        ->addNode(HierarchyNodeInput::create('R', 'Revenue')->accounts(HierarchyAccount::profitAndLoss('8000')))
        ->addNode(HierarchyNodeInput::create('C', 'Costs'))
        ->addNode(HierarchyNodeInput::create('C1', 'Staff')->accounts(HierarchyAccount::profitAndLoss('4000')), 'C')
        ->accessRights(
            HierarchyAccessInput::user($twinfield->userCode(), HierarchyAccessType::Edit),
            HierarchyAccessInput::everyone(HierarchyAccessType::None),
        );
}

try {
    $hierarchy = $hierarchies->save($input);
} catch (HierarchyException $exception) {
    foreach ($exception->messages as $message) {
        echo $message->node ?? $code, ': ', $message->code->value, ' ', $message->text, PHP_EOL;
    }

    exit(1);
}

echo 'Saved ', $hierarchy->code, ' (touched ', $hierarchy->touched, ')', PHP_EOL;

foreach (new RecursiveTreeIterator($hierarchy) as $line) {
    echo $line, PHP_EOL;
}
