<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseFieldOption;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$prefix = $argv[1] ?? 'fin.trs.head.';

$fields = array_values(array_filter(
    $twinfield->browseDefinitions()->fields(),
    static fn ($field): bool => str_starts_with($field->code, $prefix),
));

echo count($fields), ' browse field(s) starting with ', $prefix, PHP_EOL;

foreach ($fields as $index => $field) {
    $options = array_map(
        static fn (BrowseFieldOption $option): string => $option->name === null
            ? $option->value
            : sprintf('%s (%s)', $option->value, $option->name),
        $field->options,
    );

    echo sprintf(
        '[%d/%d] %s: %s%s%s',
        $index + 1,
        count($fields),
        $field->code,
        $field->dataType->value,
        $field->finder === null ? '' : ', Finder ' . $field->finder,
        $options === [] ? '' : ', values ' . implode(', ', $options),
    ), PHP_EOL;
}
