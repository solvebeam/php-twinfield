<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use LogicException;
use Psr\Http\Message\RequestInterface;

/**
 * Immutable builder for a SOAP envelope.
 * Each call to header() / body() returns a new instance, so a base builder
 * can safely be re-used across different requests.
 *
 * Example:
 *
 *   $request = SoapEnvelopeBuilder::new()
 *       ->header(new TwinfieldSessionHeader(sessionId: 'abc123'))
 *       ->body(new TwinfieldSearch(query: 'factuur'))
 *       ->toRequest(
 *           url: 'https://login.twinfield.com/webservices/session.asmx',
 *           factory: new NyholmSoapRequestFactory(),
 *       );
 */
final class SoapEnvelopeBuilder
{
    private object|null $header    = null;
    private object|null $body      = null;
    private string $namespace      = 'http://www.twinfield.com/';

    public static function new(): self
    {
        return new self();
    }

    public function header(object $header): self
    {
        $clone         = clone $this;
        $clone->header = $header;

        return $clone;
    }

    public function body(object $body): self
    {
        $clone       = clone $this;
        $clone->body = $body;

        return $clone;
    }

    public function namespace(string $namespace): self
    {
        $clone            = clone $this;
        $clone->namespace = $namespace;

        return $clone;
    }

    public function build(): SoapEnvelope
    {
        if ($this->body === null) {
            throw new LogicException('A SOAP body is required.');
        }

        return new SoapEnvelope($this->body, $this->header, $this->namespace);
    }

    public function toRequest(
        string $url,
        SoapRequestFactory $factory,
        string $method = 'POST',
    ): RequestInterface {
        return $this->build()->toRequest($url, $factory, $method);
    }
}
