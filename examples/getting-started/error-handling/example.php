<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Period\PeriodServiceFaultCode;
use SolveBeam\Twinfield\Soap\AccessDeniedException;
use SolveBeam\Twinfield\Soap\ErrorCode;
use SolveBeam\Twinfield\Soap\SoapFaultException;
use SolveBeam\Twinfield\Twinfield;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// 1. A Command & Query service with its own fault codes: a year that does not exist.
try {
    $twinfield->periods()->list(1901);
} catch (SoapFaultException $exception) {
    echo 'Type:    ', $exception->detailType, PHP_EOL;
    echo 'Code:    ', $exception->detailCode, PHP_EOL;
    echo 'Message: ', $exception->detailMessage, PHP_EOL;

    if (PeriodServiceFaultCode::tryFrom($exception->detailCode ?? '') === PeriodServiceFaultCode::YearNotFound) {
        echo '→ The year does not exist.', PHP_EOL;
    }
}

echo PHP_EOL;

// 2. A classic service with an invalid access token.
$invalid = Twinfield::create(new StaticAccessTokenProvider('invalid-token'));

try {
    $invalid->offices()->list();
} catch (SoapFaultException $exception) {
    echo 'Code:    ', $exception->detailCode, PHP_EOL;
    echo 'Source:  ', $exception->source, PHP_EOL;
    echo 'Message: ', $exception->detailMessage, PHP_EOL;

    if (ErrorCode::tryFrom($exception->detailCode ?? '') === ErrorCode::TokenInvalid) {
        echo '→ Get a new access token, or let the user log in again.', PHP_EOL;
    }
} catch (AccessDeniedException $exception) {
    echo 'HTTP ', $exception->getCode(), ': ', $exception->getMessage(), PHP_EOL;
}
