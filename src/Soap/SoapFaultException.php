<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use RuntimeException;

final class SoapFaultException extends RuntimeException
{
    public static function fromSoapResponse(SoapResponse $response): self
    {
        $faultCode = $response->faultCode() ?? 'SOAP-FAULT';
        $faultString = $response->faultString() ?? 'Unknown SOAP fault';

        return new self(sprintf('%s: %s', $faultCode, $faultString));
    }
}
