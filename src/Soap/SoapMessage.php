<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use Psr\Http\Message\RequestInterface;

/**
 * A SOAP message that can be sent with SoapClient::sendEnvelope().
 *
 * Implemented by SoapEnvelope (classic .asmx services) and
 * CommandQueryEnvelope (Command & Query .svc services).
 */
interface SoapMessage
{
    public function toRequest(
        string $url,
        SoapRequestFactory $factory,
        string $method = 'POST',
    ): RequestInterface;
}
