<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first activity.
$code = $argv[1] ?? $twinfield->activities()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no activities.' . PHP_EOL);

try {
    $activity = $twinfield->activities()->get($code);
} catch (DimensionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$projects = $activity->projects;

echo 'Code:        ', $activity->code, PHP_EOL;
echo 'Name:        ', $activity->name, PHP_EOL;
echo 'Short name:  ', $activity->shortName ?? '-', PHP_EOL;
echo 'Status:      ', $activity->status->value, $activity->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Modified:    ', $activity->modified?->format('Y-m-d H:i') ?? '-', PHP_EOL;
echo 'VAT code:    ', $activity->vatCode ?? '-', PHP_EOL;
echo 'Valid from:  ', $projects->validFrom?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Valid till:  ', $projects->validTill?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Customer:    ', $projects->customer->code ?? '-', ' (', $projects->customer->change->name, ')', PHP_EOL;
echo 'Rate:        ', $projects->rate->code ?? '-', ' (', $projects->rate->change->name, ')', PHP_EOL;
echo 'Billable:    ', match ($projects->billable->billable) {
    true => 'yes',
    false => 'no',
    null => '-',
}, ' (', $projects->billable->change->name, ')', PHP_EOL;

foreach ($projects->quantities as $quantity) {
    echo 'Quantity:    ', $quantity->label, $quantity->mandatory ? ' (mandatory)' : '', PHP_EOL;
}
