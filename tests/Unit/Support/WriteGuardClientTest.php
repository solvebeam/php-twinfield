<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Support;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\Http\WriteBlockedException;
use SolveBeam\Twinfield\Tests\Support\Http\WriteGuardClient;

/**
 * The write guard protects the production organisation, so it gets its own tests.
 */
final class WriteGuardClientTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function requests(): iterable
    {
        yield 'finder search' => ['Search', self::envelope('<Search />'), true];
        yield 'get' => ['GetAllSummaries', self::envelope('<GetAllSummaries />'), true];
        yield 'process xml read' => ['ProcessXmlString', self::processXml('<read><type>dimensions</type></read>'), true];
        yield 'process xml browse' => ['ProcessXmlString', self::processXml('<columns code="000" />'), true];
        yield 'process xml general with reads' => ['ProcessXmlString', self::processXml('<general><read /><list /></general>'), true];
        yield 'process xml write' => ['ProcessXmlString', self::processXml('<dimension><office>1002</office></dimension>'), false];
        yield 'process xml mixed' => ['ProcessXmlString', self::processXml('<general><read /><transaction /></general>'), false];
        yield 'process xml unparsable' => ['ProcessXmlString', self::processXml('not xml'), false];
        yield 'compressed (cannot inspect)' => ['ProcessXmlCompressed', self::envelope('<ProcessXmlCompressed />'), false];
        yield 'unknown action' => ['SetToApproved', self::envelope('<SetToApproved />'), false];
    }

    #[DataProvider('requests')]
    public function testClassifiesRequests(string $action, string $body, bool $readOnly): void
    {
        self::assertSame($readOnly, WriteGuardClient::isReadOnly($action, $body));
    }

    public function testLetsReadsThrough(): void
    {
        $guard = $this->guard(allowWrites: null);

        $response = $guard->sendRequest($this->request('Search', self::envelope('<Search />', '1000')));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testBlocksUnconfirmedWrites(): void
    {
        $this->expectException(WriteBlockedException::class);
        $this->expectExceptionMessage('writes not confirmed');

        $this->guard(allowWrites: null)->sendRequest($this->request('ProcessXmlString', self::processXml('<dimension />', '1002')));
    }

    public function testBlocksWritesOutsideAllowlistEvenWhenConfirmed(): void
    {
        $this->expectException(WriteBlockedException::class);
        $this->expectExceptionMessage('not in TWINFIELD_WRITE_COMPANIES');

        $this->guard(allowWrites: '1000')->sendRequest($this->request('ProcessXmlString', self::processXml('<dimension />', '1000')));
    }

    public function testBlocksWritesWithOfficeOutsideConfirmedCompany(): void
    {
        $this->expectException(WriteBlockedException::class);
        $this->expectExceptionMessage('company "1000"');

        $body = self::processXml('<dimension><office>1000</office></dimension>', '1002');

        $this->guard(allowWrites: '1002')->sendRequest($this->request('ProcessXmlString', $body));
    }

    public function testAllowsConfirmedWrites(): void
    {
        $body = self::processXml('<dimension><office>1002</office></dimension>', '1002');

        $response = $this->guard(allowWrites: '1002')->sendRequest($this->request('ProcessXmlString', $body));

        self::assertSame(200, $response->getStatusCode());
    }

    private function guard(?string $allowWrites): WriteGuardClient
    {
        return new WriteGuardClient(new ReplayClient(new Response(200)), ['1002', '1002_3'], $allowWrites);
    }

    private function request(string $action, string $body): Request
    {
        return new Request('POST', 'https://example.test/webservices/processxml.asmx', [
            'SOAPAction' => 'http://www.twinfield.com/' . $action,
        ], $body);
    }

    private static function envelope(string $body, string $company = '1002'): string
    {
        return <<<XML
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://www.twinfield.com/">
              <soap:Header><Header><AccessToken>x</AccessToken><CompanyCode>{$company}</CompanyCode></Header></soap:Header>
              <soap:Body>{$body}</soap:Body>
            </soap:Envelope>
            XML;
    }

    private static function processXml(string $xml, string $company = '1002'): string
    {
        $escaped = htmlspecialchars($xml, ENT_XML1);

        return self::envelope("<ProcessXmlString><xmlRequest>{$escaped}</xmlRequest></ProcessXmlString>", $company);
    }
}
