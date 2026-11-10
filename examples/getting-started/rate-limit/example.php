<?php

declare(strict_types=1);

use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Twinfield;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

/**
 * Prints what the last request cost and how many API credits are left in the current minute.
 */
$show = static function (string $request, Twinfield $twinfield): void {
    $rateLimit = $twinfield->lastRateLimit();

    if ($rateLimit === null) {
        echo $request, ': no rate limit headers', PHP_EOL;

        return;
    }

    echo $request, ': cost ', $rateLimit->credited ?? '?',
        ', left ', $rateLimit->remaining ?? '?', ' of ', $rateLimit->limit ?? '?',
        ', for the client ID ', $rateLimit->clientIdRemaining ?? '?', ' of ', $rateLimit->clientIdLimit ?? '?',
        PHP_EOL;
};

// The Finder: SOAP action Search.
$twinfield->finder(FinderType::Countries)->limit(1)->get();
$show('Finder', $twinfield);

// An XML web service: ProcessXml. This read has the root element <dimensiontype>, not <read>,
// so Twinfield does not count it as a query request.
$twinfield->dimensionTypes()->get(DimensionTypeCode::AccountsReceivable);
$show('ProcessXml', $twinfield);

// A Command & Query service: SOAP action Query.
$twinfield->periods()->years();
$show('Command & Query', $twinfield);

// The REST API: HTTP GET.
$twinfield->rest(
    'GET',
    '/organisations/{organisationId}/companies/{companyId}/fixedassets/classes',
    ['limit' => 1],
);
$show('REST GET', $twinfield);

// Slow down before the limit is reached, instead of after a 429.
$rateLimit = $twinfield->lastRateLimit();

if ($rateLimit?->remaining !== null && $rateLimit->remaining < 10) {
    echo 'Almost out of API credits: wait for the next minute.', PHP_EOL;
}
