<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$declarations = $twinfield->declarations();
$year = (int) ($argv[1] ?? date('Y'));

echo 'Company ', $twinfield->companyCode(), ': ', $declarations->count(), ' returns in all years', PHP_EOL;

$returns = $declarations->list(limit: 1000, year: $year);

if ($returns === []) {
    echo 'No returns in ', $year, '; the returns of every company of the organisation:', PHP_EOL;

    $returns = $declarations->listForOrganisation(limit: 10, year: $year);
}

foreach ($returns as $return) {
    echo sprintf(
        '%6d  %-6s %-16s %-22s %s',
        $return->id,
        $return->company->code,
        $return->type->value,
        $return->period,
        $return->status->step->name,
    ), PHP_EOL;
}
