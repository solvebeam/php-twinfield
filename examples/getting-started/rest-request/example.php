<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

echo 'Company ', $twinfield->companyCode(), ' has GUID ', $twinfield->companyId(), PHP_EOL;

// A path from the Swagger definition: the SDK fills in the GUIDs of the organisation and company.
$response = $twinfield->rest(
    'GET',
    '/organisations/{organisationId}/companies/{companyId}/fixedassets/classes',
    ['fields' => '*', 'limit' => 5],
);

$json = $response->json();

echo $json->int('filteredTotal'), ' fixed asset classes', PHP_EOL;

foreach ($json->list('items') as $class) {
    echo $class->string('name'), ': ', $class->string('method'), ', lifetime ', $class->decimal('lifetime'), PHP_EOL;
}
