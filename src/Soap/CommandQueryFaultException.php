<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use InvalidArgumentException;

/**
 * SOAP fault of a Command & Query web service (.svc):
 * `<detail><PeriodServiceFault><Code>YearNotFound</Code><Message>…</Message></PeriodServiceFault></detail>`.
 *
 * The code only has meaning within its fault type, e.g. PeriodServiceFaultCode for `PeriodServiceFault`:
 *
 *   try {
 *       $twinfield->periods()->list(1901);
 *   } catch (CommandQueryFaultException $exception) {
 *       if (PeriodServiceFaultCode::tryFrom($exception->errorCode) === PeriodServiceFaultCode::YearNotFound) { … }
 *   }
 *
 * @see https://developers.twinfield.com/documentation/api/types/command-and-query-webservices
 */
final class CommandQueryFaultException extends SoapFaultException
{
    public function __construct(
        string $message,
        ?string $faultCode,
        ?string $faultString,
        /** Fault type, e.g. `PeriodServiceFault`, `SecurityFault` or `ValidationFault`. */
        public readonly string $type,
        /** E.g. `YearNotFound`. Use this to act on an error. */
        public readonly string $errorCode,
        /** Readable text, in the language of the user. Only for logging or showing to the user. */
        public readonly string $errorMessage,
    ) {
        if ($type === '') {
            throw new InvalidArgumentException('The type of a SOAP fault cannot be empty.');
        }

        if ($errorCode === '') {
            throw new InvalidArgumentException('The error code of a SOAP fault cannot be empty.');
        }

        parent::__construct($message, $faultCode, $faultString);
    }
}
