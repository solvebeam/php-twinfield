<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Office;

use DOMDocument;
use DOMElement;
use DOMXPath;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Office\OfficeNotFoundException;
use SolveBeam\Twinfield\Office\OfficeSummary;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Soap\ClassicServiceFaultException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class OfficeResourceTest extends TestCase
{
    public function testListsOfficesOverAllPages(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-offices-with-id-page-1'),
            ReplayClient::response('finder-offices-with-id-page-2'),
        );

        // TotalRows says 3, so the resource keeps fetching until it has all of them.
        $offices = $this->twinfield($http)->offices()->list();

        self::assertEquals([
            new OfficeSummary(TestTokens::organisation(), '1000', 'Example Holding B.V.', '0f8fad5b-d9cb-469f-a165-70867728950e', 'NL', 1, 21),
            new OfficeSummary(TestTokens::organisation(), '1001', 'Example Retail & Co B.V.', '7c9e6679-7425-40de-944b-e07fc1f90ae7', 'NL', 0, 30),
            new OfficeSummary(TestTokens::organisation(), '1002', 'Example Services B.V.', 'f47ac10b-58cc-4372-a567-0e02b2c3d479', 'BE', 2, 21),
        ], $offices);

        $xpath = $this->soapXpath($http->requests[1]);

        self::assertSame('OFF', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('3', $xpath->evaluate('string(//tw:Search/tw:firstRow)'));
        self::assertSame(['includeid' => '1'], $this->finderOptions($xpath));
    }

    public function testSearchesOffices(): void
    {
        $http = ReplayClient::fixture('finder-offices-with-id-page-1');

        $page = $this->twinfield($http)->offices()->search('*Example*')
            ->inName()
            ->withoutTaxGroup()
            ->limit(2)
            ->get();

        self::assertSame(3, $page->totalRows);
        self::assertSame(['1000', '1001'], array_map(
            static fn (OfficeSummary $office): string => $office->code,
            iterator_to_array($page),
        ));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('*Example*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('2', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('2', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
        self::assertSame(['includeid' => '1', 'withouttaxgroup' => '1'], $this->finderOptions($xpath));
    }

    public function testThrowsOnASearchRowWithoutCompanyId(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Finder office row 0 has no code, name, company ID or country');

        $this->twinfield(ReplayClient::fixture('finder-offices-with-id-missing-id'))->offices()->list();
    }

    public function testThrowsWhenTheIdColumnIsMissing(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Finder office row 0 has 5 columns, expected 6');

        $this->twinfield(ReplayClient::fixture('finder-offices-without-id'))->offices()->list();
    }

    public function testReadsAnOfficeWithTheCompanyOfTheClient(): void
    {
        $http = ReplayClient::fixture('office-read');

        $office = $this->twinfield($http)->company('1001')->offices()->get('1000');

        self::assertSame('1000', $office->code);
        self::assertSame('<read><type>office</type><code>1000</code></read>', $this->xmlRequest($http->lastRequest()));
        self::assertSame('1001', $this->companyHeader($http->lastRequest()));
    }

    public function testReadsAnOfficeWithoutCompanyUsingTheOfficeItself(): void
    {
        $http = ReplayClient::fixture('office-read');

        $this->twinfield($http)->offices()->get('1000');

        self::assertSame('1000', $this->companyHeader($http->lastRequest()));
    }

    public function testReadsTheCurrentOffice(): void
    {
        $http = ReplayClient::fixture('office-read');

        self::assertSame('1000', $this->twinfield($http)->company('1000')->offices()->current()->code);
        self::assertStringContainsString('<code>1000</code>', $this->xmlRequest($http->lastRequest()));
    }

    public function testCurrentNeedsACompany(): void
    {
        $this->expectException(LogicException::class);

        $this->twinfield(new ReplayClient())->offices()->current();
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('office-read-not-found'))->company('1000')->offices()->get('9999');
            self::fail('Expected an OfficeNotFoundException.');
        } catch (OfficeNotFoundException $exception) {
            self::assertSame('9999', $exception->officeCode);
            self::assertSame('Administratie (9999) bestaat niet.', $exception->reason);
            self::assertInstanceOf(ProcessXmlException::class, $exception->getPrevious());
        }
    }

    public function testThrowsNotFoundWithoutCompany(): void
    {
        // Without company the office itself is the company; Twinfield then refuses with 359.
        try {
            $this->twinfield(ReplayClient::fixture('office-fault-no-company', 500))->offices()->get('9999');
            self::fail('Expected an OfficeNotFoundException.');
        } catch (OfficeNotFoundException $exception) {
            self::assertSame('9999', $exception->officeCode);
            self::assertInstanceOf(ClassicServiceFaultException::class, $exception->getPrevious());
        }
    }

    public function testKeepsTheFaultWhenTheClientHasACompany(): void
    {
        $this->expectException(ClassicServiceFaultException::class);

        $http = ReplayClient::fixture('office-fault-no-company', 500);

        $this->twinfield($http)->company('1000')->offices()->get('9999');
    }

    public function testKeepsOtherErrors(): void
    {
        $this->expectException(ProcessXmlException::class);

        $this->twinfield(ReplayClient::fixture('office-read-error'))->company('1000')->offices()->get('1000');
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $offices = $this->twinfield(ReplayClient::fixture('office-read-not-found'))->company('1000')->offices();

        self::assertNull($offices->find('9999'));
    }

    public function testCompanyAcceptsAnOffice(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-offices-with-id-page-1'),
            ReplayClient::response('finder-offices-with-id-page-2'),
        );
        $twinfield = $this->twinfield($http);

        $summary = $twinfield->offices()->list()[1];

        self::assertSame('1001', $twinfield->company($summary)->companyCode());
        self::assertNull($twinfield->companyCode());
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        $accessTokenProvider = TestTokens::provider('https://example.test');

        return Twinfield::create($accessTokenProvider, $http, $psr17, $psr17);
    }

    private function soapXpath(RequestInterface $request): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML((string) $request->getBody());

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        return $xpath;
    }

    private function xmlRequest(RequestInterface $request): string
    {
        $xmlRequest = $this->soapXpath($request)->evaluate('string(//tw:ProcessXmlString/tw:xmlRequest)');

        self::assertIsString($xmlRequest);

        return $xmlRequest;
    }

    private function companyHeader(RequestInterface $request): string
    {
        $company = $this->soapXpath($request)->evaluate('string(//soap:Header/tw:Header/tw:CompanyCode)');

        self::assertIsString($company);

        return $company;
    }

    /**
     * @return array<string, string>
     */
    private function finderOptions(DOMXPath $xpath): array
    {
        $options = [];
        $nodes = $xpath->query('//tw:Search/tw:options/tw:ArrayOfString');

        foreach ($nodes === false ? [] : $nodes as $option) {
            self::assertInstanceOf(DOMElement::class, $option);

            $name = $xpath->evaluate('string(tw:string[1])', $option);
            $value = $xpath->evaluate('string(tw:string[2])', $option);

            self::assertIsString($name);
            self::assertIsString($value);

            $options[$name] = $value;
        }

        return $options;
    }
}
