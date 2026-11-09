<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\ProcessXml;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use SolveBeam\Twinfield\ProcessXml\MessageType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlString;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class ProcessXmlTest extends TestCase
{
    public function testListsCompaniesWithoutCompanyInHeader(): void
    {
        $http = ReplayClient::fixture('processxml-list-offices');

        $result = $this->twinfield($http)->processXml('<list><type>offices</type></list>');

        self::assertTrue($result->successful());
        self::assertSame('offices', $result->root()->localName);
        self::assertSame([], $result->messages());
        $simpleXml = simplexml_load_string($result->xml());
        self::assertInstanceOf(SimpleXMLElement::class, $simpleXml);
        self::assertSame('offices', $simpleXml->getName());
        $offices = $result->xpath();

        self::assertSame('Example Retail & Co B.V.', $offices->evaluate('string(/offices/office[2]/@name)'));
        self::assertSame('1001', $offices->evaluate('string(/offices/office[2])'));

        $request = $http->lastRequest();
        $xpath = $this->requestXpath((string) $request->getBody());

        self::assertSame('https://example.test/webservices/processxml.asmx', (string) $request->getUri());
        self::assertSame('http://www.twinfield.com/ProcessXmlString', $request->getHeaderLine('SOAPAction'));
        self::assertSame('token-123', $xpath->evaluate('string(//tw:Header/tw:AccessToken)'));
        self::assertSame(0.0, $xpath->evaluate('count(//tw:Header/tw:CompanyCode)'));
        self::assertSame(
            '<list><type>offices</type></list>',
            $xpath->evaluate('string(/soap:Envelope/soap:Body/tw:ProcessXmlString/tw:xmlRequest)'),
        );
    }

    public function testSendsCompanyCode(): void
    {
        $http = ReplayClient::fixture('processxml-list-offices');

        $this->twinfield($http)->company('1000')->processXml('<list><type>offices</type></list>');

        $xpath = $this->requestXpath((string) $http->lastRequest()->getBody());

        self::assertSame('1000', $xpath->evaluate('string(//tw:Header/tw:CompanyCode)'));
    }

    public function testUsesNoNamespacePrefixOnTheBody(): void
    {
        $http = ReplayClient::fixture('processxml-list-offices');

        $this->twinfield($http)->processXml('<list><type>offices</type></list>');

        // Twinfield gives HTTP 400 on prefixed elements such as <tns:ProcessXmlString>.
        $body = (string) $http->lastRequest()->getBody();

        self::assertStringContainsString('<ProcessXmlString xmlns="http://www.twinfield.com/">', $body);
    }

    public function testRemovesCommentsAndWhitespaceBetweenElements(): void
    {
        $request = ProcessXmlString::fromXml(<<<'XML'
            <?xml version="1.0" encoding="ISO-8859-1"?>
            <read>
                <!-- a dimension -->
                <type>dimensions</type>
                <name>Caf&#233; &amp; Co </name>
            </read>
            XML);

        self::assertSame('<read><type>dimensions</type><name>Café &amp; Co </name></read>', $request->xmlRequest);
    }

    public function testRejectsInvalidXml(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The ProcessXml request is not valid XML');

        ProcessXmlString::fromXml('<list><type>offices</list>');
    }

    public function testRejectsDeprecatedGeneralRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The <general> root element is deprecated');

        ProcessXmlString::fromXml('<general><dimensions /><transactions /></general>');
    }

    public function testThrowsWithAllMessagesWhenNotProcessed(): void
    {
        try {
            $http = ReplayClient::fixture('processxml-transactions-error');

            $this->twinfield($http)->company('1000')->processXml('<transactions />');
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            self::assertSame(
                'transactions/transaction[2]/header/period: Period 2021/6 is closed.',
                $exception->getMessage(),
            );

            $result = $exception->result;

            self::assertFalse($result->successful());

            $messages = $result->messages();

            self::assertCount(2, $messages);
            self::assertSame(MessageType::Warning, $messages[0]->type);
            self::assertSame('The date is in another period.', $messages[0]->text);
            self::assertSame('transactions/transaction[1]/header/date', $messages[0]->path);
            self::assertSame('20250205', $messages[0]->element->textContent);
            self::assertSame(MessageType::Error, $messages[1]->type);
            self::assertCount(1, $result->errors());
            self::assertCount(1, $result->warnings());

            // The first transaction was processed, with the extra attributes Twinfield adds.
            self::assertSame('1', $result->xpath()->evaluate('string(/transactions/transaction[1]/@result)'));
            self::assertSame('Euro', $result->xpath()->evaluate('string(//transaction[1]/header/currency/@name)'));
        }
    }

    public function testThrowsWhenTheResultAttributeIsMissing(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('The <offices> response has no valid result attribute: "".');

        $http = ReplayClient::fixture('processxml-missing-result');

        $this->twinfield($http)->processXml('<list><type>offices</type></list>');
    }

    public function testThrowsWhenTheResultElementIsMissing(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('ProcessXmlStringResponse/ProcessXmlStringResult is missing.');

        $http = ReplayClient::fixture('processxml-no-result-element');

        $this->twinfield($http)->processXml('<list><type>offices</type></list>');
    }

    public function testKeepsTheResponseAsString(): void
    {
        $xml = '<dimension result="1"><name name="x">Café &amp; Co</name></dimension>';

        self::assertSame($xml, ProcessXmlResult::fromXml($xml)->xml());
    }

    public function testThrowsOnAnUnknownMessageType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/name has a message with an unknown msgtype "": Name is required.');

        $result = ProcessXmlResult::fromXml('<dimension result="0"><name msg="Name is required." /></dimension>');

        $result->messages();
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        $tokens = TestTokens::provider('https://example.test');

        return Twinfield::create($tokens, $http, $psr17, $psr17);
    }

    private function requestXpath(string $xml): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML($xml);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        return $xpath;
    }
}
