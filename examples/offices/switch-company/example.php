<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Finder\FinderType;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

foreach (array_slice($twinfield->offices()->list(), 0, 3) as $office) {
    // company() returns a new client; $twinfield itself does not change.
    $company = $twinfield->company($office);

    $dimensions = $company->finder(FinderType::Dimensions)->limit(1)->get()->totalRows;

    echo "{$office->code} {$office->name}: {$dimensions} dimensions", PHP_EOL;
}

echo 'The default company is still ', $twinfield->companyCode(), PHP_EOL;
