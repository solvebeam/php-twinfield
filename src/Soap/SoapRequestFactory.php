<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Abstracts PSR-7 / PSR-17 object creation so the SOAP layer stays
 * independent of the concrete HTTP library (Guzzle, Nyholm, etc.).
 *
 * @internal
 */
interface SoapRequestFactory
{
    public function createRequest(string $method, string $url): RequestInterface;

    public function createStream(string $body): StreamInterface;
}
