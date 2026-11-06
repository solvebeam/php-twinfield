<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Soap;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SolveBeam\Twinfield\Soap\SoapResponse;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;

final class SoapResponseTest extends TestCase
{
    public function testReadsSoap11Fault(): void
    {
        $response = SoapResponse::fromHttpResponse(ReplayClient::response('fault-soap11', 500));

        self::assertTrue($response->isFault());
        self::assertSame('soap:Client', $response->faultCode());
        self::assertSame('Access denied. Token invalid.', $response->faultString());
        self::assertNull($response->payloadElement());
    }

    public function testReadsSoap12Fault(): void
    {
        $response = SoapResponse::fromHttpResponse(ReplayClient::response('fault-soap12', 500));

        self::assertTrue($response->isFault());
        self::assertSame('soap:Sender', $response->faultCode());
        self::assertSame('Company code is invalid.', $response->faultString());
    }

    public function testExposesPayloadElement(): void
    {
        $response = SoapResponse::fromHttpResponse(ReplayClient::response('finder-search-dim'));

        self::assertFalse($response->isFault());
        self::assertSame('SearchResponse', $response->payloadElement()?->localName);
    }

    public function testRejectsXmlWithoutEnvelope(): void
    {
        $this->expectException(RuntimeException::class);

        SoapResponse::fromHttpResponse(new Response(200, [], '<?xml version="1.0"?><html />'));
    }
}
