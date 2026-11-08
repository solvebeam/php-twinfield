<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\CodeSetting;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first project.
$code = $argv[1] ?? $twinfield->projects()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no projects.' . PHP_EOL);

try {
    $project = $twinfield->projects()->get($code);
} catch (DimensionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$settings = $project->projects;

// A code with its change mode, e.g. "1010 (Disallow)" or "- (Inherit)".
$setting = static fn (CodeSetting $setting): string => ($setting->code ?? '-') . ' (' . $setting->change->name . ')';

echo 'Code:        ', $project->code, PHP_EOL;
echo 'Name:        ', $project->name, PHP_EOL;
echo 'Short name:  ', $project->shortName ?? '-', PHP_EOL;
echo 'Status:      ', $project->status->value, $project->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Modified:    ', $project->modified?->format('Y-m-d H:i') ?? '-', PHP_EOL;
echo 'VAT code:    ', $project->vatCode ?? '-', PHP_EOL;
echo 'Valid from:  ', $settings->validFrom?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Valid till:  ', $settings->validTill?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Invoice:     ', $settings->invoiceDescription ?? '-', PHP_EOL;
echo 'Customer:    ', $setting($settings->customer), PHP_EOL;
echo 'Authoriser:  ', $setting($settings->authoriser), PHP_EOL;
echo 'Rate:        ', $setting($settings->rate), PHP_EOL;
echo 'Billable:    ', match ($settings->billable->billable) {
    true => 'yes',
    false => 'no',
    null => '-',
}, ' (', $settings->billable->change->name, ')', $settings->billable->forRatio ? ', counts for the ratio' : '', PHP_EOL;

foreach ($settings->quantities as $quantity) {
    echo 'Quantity:    ', $quantity->label, $quantity->mandatory ? ' (mandatory)' : '', PHP_EOL;
}
