<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Period\PeriodServiceFaultCode;
use SolveBeam\Twinfield\Soap\AccessDeniedException;
use SolveBeam\Twinfield\Soap\ClassicServiceFaultException;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Soap\ErrorCode;
use SolveBeam\Twinfield\Twinfield;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// 1. A Command & Query service with its own fault codes: a year that does not exist.
try {
    $twinfield->periods()->list(1901);
} catch (CommandQueryFaultException $exception) {
    echo 'Type:    ', $exception->type, PHP_EOL;
    echo 'Code:    ', $exception->errorCode, PHP_EOL;
    echo 'Message: ', $exception->errorMessage, PHP_EOL;

    if (PeriodServiceFaultCode::tryFrom($exception->errorCode) === PeriodServiceFaultCode::YearNotFound) {
        echo '→ The year does not exist.', PHP_EOL;
    }
}

echo PHP_EOL;

// 2. A classic service with an invalid access token (for the same organisation and user).
$invalid = Twinfield::create(
    new StaticAccessTokenProvider('invalid-token', $twinfield->organisation(), $twinfield->userCode()),
);

try {
    $invalid->offices()->list();
} catch (ClassicServiceFaultException $exception) {
    echo 'Code:    ', $exception->errorCode, PHP_EOL;
    echo 'Source:  ', $exception->source, PHP_EOL;
    echo 'Message: ', $exception->errorMessage, PHP_EOL;

    if (ErrorCode::tryFrom($exception->errorCode) === ErrorCode::TokenInvalid) {
        echo '→ Get a new access token, or let the user log in again.', PHP_EOL;
    }
} catch (AccessDeniedException $exception) {
    echo 'HTTP ', $exception->getCode(), ': ', $exception->getMessage(), PHP_EOL;
}
