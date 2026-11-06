<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Soap;

use DOMDocument;
use DOMXPath;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Authentication\AuthenticationHeader;
use SolveBeam\Twinfield\Data\ArrayOfArrayOfString;
use SolveBeam\Twinfield\Data\ArrayOfString;
use SolveBeam\Twinfield\Finder\Search;
use SolveBeam\Twinfield\Soap\Psr17SoapRequestFactory;
use SolveBeam\Twinfield\Soap\SoapEnvelope;
use stdClass;

final class SoapEnvelopeTest extends TestCase
{
    public function testBuildsEnvelopeWithHeaderAndBody(): void
    {
        $envelope = new SoapEnvelope(
            new Search(type: 'DIM', pattern: '*', field: 0, firstRow: 1, maxRows: 10),
            new AuthenticationHeader(accessToken: 'token-123', companyCode: '1002'),
        );

        $xpath = $this->xpath($envelope->toXml());

        self::assertSame('token-123', $xpath->evaluate('string(/soap:Envelope/soap:Header/tw:Header/tw:AccessToken)'));
        self::assertSame('1002', $xpath->evaluate('string(/soap:Envelope/soap:Header/tw:Header/tw:CompanyCode)'));
        self::assertSame('DIM', $xpath->evaluate('string(/soap:Envelope/soap:Body/tw:Search/tw:type)'));
        self::assertSame('*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('10', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
    }

    public function testEscapesValues(): void
    {
        $envelope = new SoapEnvelope(new Search(type: 'DIM', pattern: 'M&amp;Z & <co>', field: 0, firstRow: 1, maxRows: 10));

        self::assertSame('M&amp;Z & <co>', $this->xpath($envelope->toXml())->evaluate('string(//tw:Search/tw:pattern)'));
    }

    public function testSkipsNullProperties(): void
    {
        $envelope = new SoapEnvelope(new Search(type: 'DIM', pattern: '*', field: 0, firstRow: 1, maxRows: 10));

        self::assertSame(0.0, $this->xpath($envelope->toXml())->evaluate('count(//tw:Search/tw:options)'));
    }

    public function testSerializesSoapArrays(): void
    {
        $options = new ArrayOfArrayOfString([
            new ArrayOfString(['dimtype', 'DEB']),
            new ArrayOfString(['office', '1002']),
        ]);

        $envelope = new SoapEnvelope(new Search('DIM', '*', 0, 1, 10, $options));
        $xpath = $this->xpath($envelope->toXml());

        self::assertSame(2.0, $xpath->evaluate('count(//tw:options/tw:ArrayOfString)'));
        self::assertSame('DEB', $xpath->evaluate('string(//tw:options/tw:ArrayOfString[1]/tw:string[2])'));
        self::assertSame('office', $xpath->evaluate('string(//tw:options/tw:ArrayOfString[2]/tw:string[1])'));
    }

    public function testCreatesRequestWithSoapActionHeader(): void
    {
        $psr17 = new Psr17Factory();
        $envelope = new SoapEnvelope(new Search('DIM', '*', 0, 1, 10));

        $request = $envelope->toRequest('https://example.test/webservices/finder.asmx', new Psr17SoapRequestFactory($psr17, $psr17));

        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://www.twinfield.com/Search', $request->getHeaderLine('SOAPAction'));
        self::assertSame('text/xml; charset=utf-8', $request->getHeaderLine('Content-Type'));
        self::assertSame($envelope->toXml(), (string) $request->getBody());
    }

    public function testRequiresSoapNameOnBody(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must be annotated with #[');

        (new SoapEnvelope(new stdClass()))->toXml();
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($xml), 'Envelope is valid XML.');

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        return $xpath;
    }
}
