<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * SOAP request factory backed by any PSR-17 implementation.
 *
 * Example with nyholm/psr7:
 *
 *   $psr17 = new \Nyholm\Psr7\Factory\Psr17Factory();
 *   $factory = new Psr17SoapRequestFactory($psr17, $psr17);
 *
 * @internal
 */
final readonly class Psr17SoapRequestFactory implements SoapRequestFactory
{
    public function __construct(
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function createRequest(string $method, string $url): RequestInterface
    {
        return $this->requestFactory->createRequest($method, $url);
    }

    public function createStream(string $body): StreamInterface
    {
        return $this->streamFactory->createStream($body);
    }
}
