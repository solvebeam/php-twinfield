<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$years = $twinfield->periods()->years();

echo 'Company ', $twinfield->companyCode(), PHP_EOL;

if ($years === []) {
    echo 'The company has no years yet.', PHP_EOL;

    exit;
}

echo count($years), ' years, from ', min($years), ' to ', max($years), PHP_EOL;

foreach ($twinfield->periods()->list(max($years)) as $period) {
    echo sprintf(
        '%2d  %-35s %-10s  %s',
        $period->number,
        $period->name,
        $period->endDate?->format('Y-m-d') ?? '-',
        $period->open ? 'open' : 'closed',
    ), PHP_EOL;
}
