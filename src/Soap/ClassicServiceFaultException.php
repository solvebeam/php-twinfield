<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use InvalidArgumentException;

/**
 * SOAP fault of a classic web service (.asmx):
 * `<detail><message>…</message><code>354</code><source>KeepAlive</source></detail>`.
 *
 * The code is one of the general error codes in ErrorCode:
 *
 *   try {
 *       $twinfield->offices()->list();
 *   } catch (ClassicServiceFaultException $exception) {
 *       if (ErrorCode::tryFrom($exception->errorCode) === ErrorCode::TokenInvalid) { … }
 *   }
 *
 * @see https://developers.twinfield.com/documentation/api/types/guidelines
 */
final class ClassicServiceFaultException extends SoapFaultException
{
    public function __construct(
        string $message,
        ?string $faultCode,
        ?string $faultString,
        /** E.g. `354`, see ErrorCode. Use this to act on an error. */
        public readonly string $errorCode,
        /** Readable text, in the language of the user. Only for logging or showing to the user. */
        public readonly string $errorMessage,
        /** Name of the called method, e.g. `KeepAlive`. Not always present. */
        public readonly ?string $source = null,
    ) {
        if ($errorCode === '') {
            throw new InvalidArgumentException('The error code of a SOAP fault cannot be empty.');
        }

        parent::__construct($message, $faultCode, $faultString);
    }
}
