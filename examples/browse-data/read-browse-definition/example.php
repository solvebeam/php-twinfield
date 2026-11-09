<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$code = $argv[1] ?? '030_3';

$definition = $twinfield->browseDefinitions()->get($code);

echo 'Browse code ', $definition->code, ': ', $definition->name, ' (company ', $definition->company->code, ')', PHP_EOL;
echo count($definition->columns), ' fields, ', count($definition->criteria()), ' of them can be a criterion', PHP_EOL;

foreach ($definition->columns as $column) {
    $criterion = '';

    if ($column->ask) {
        $criterion = ' criterion: ' . $column->operator->value;

        if ($column->from !== null) {
            $criterion .= sprintf(' (proposed %s%s)', $column->from, $column->to === null ? '' : ' … ' . $column->to);
        }
    }

    echo rtrim(sprintf(
        '[%d/%d] %-40s %-30s%s%s',
        $column->id,
        count($definition->columns),
        $column->field,
        $column->label,
        $column->visible ? '' : ' (criterion only)',
        $criterion,
    )), PHP_EOL;
}
