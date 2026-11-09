<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Authentication\AuthenticationHeader;
use SolveBeam\Twinfield\CommandQuery\Command;
use SolveBeam\Twinfield\CommandQuery\CommandQueryEnvelope;
use SolveBeam\Twinfield\Soap\Psr17SoapRequestFactory;
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
        yield 'process xml asset method read' => ['ProcessXmlString', self::processXml('<assetmethod><office>1002</office><code>10JRGEB</code></assetmethod>'), true];
        yield 'process xml asset method save' => ['ProcessXmlString', self::processXml('<assetmethod><office>1002</office><code>M</code><name>Machine</name></assetmethod>'), false];
        yield 'process xml asset method status' => ['ProcessXmlString', self::processXml('<assetmethod status="deleted"><office>1002</office><code>M</code></assetmethod>'), false];
        yield 'process xml asset method code only' => ['ProcessXmlString', self::processXml('<assetmethod><code>M</code><code>N</code></assetmethod>'), false];
        yield 'process xml reversal info' => ['ProcessXmlString', self::processXml('<reverse action="getreversetransactioninfo"><original><office>1002</office><code>MEMO</code><number>202100001</number></original></reverse>'), true];
        yield 'process xml reversal without action' => ['ProcessXmlString', self::processXml('<reverse><original><office>1002</office><code>MEMO</code><number>202100001</number></original></reverse>'), false];
        yield 'process xml reversal post final' => ['ProcessXmlString', self::processXml('<reverse action="postfinal"><original><office>1002</office><code>MEMO</code><number>202100001</number></original></reverse>'), false];
        yield 'process xml reversal undo' => ['ProcessXmlString', self::processXml('<reverse action="undo"><original><office>1002</office><code>MEMO</code><number>202100001</number></original></reverse>'), false];
        yield 'process xml dimension type read' => ['ProcessXmlString', self::processXml('<dimensiontype><office>1002</office><code>DEB</code></dimensiontype>'), true];
        yield 'process xml dimension type save' => ['ProcessXmlString', self::processXml('<dimensiontype><office>1002</office><code>DEB</code><name>Debiteuren</name></dimensiontype>'), false];
        yield 'process xml dimension by code' => ['ProcessXmlString', self::processXml('<dimension><office>1002</office><code>1000</code></dimension>'), false];
        yield 'process xml mixed' => ['ProcessXmlString', self::processXml('<general><read /><transaction /></general>'), false];
        yield 'process xml unparsable' => ['ProcessXmlString', self::processXml('not xml'), false];
        yield 'compressed (cannot inspect)' => ['ProcessXmlCompressed', self::envelope('<ProcessXmlCompressed />'), false];
        yield 'command & query: query' => ['Query', self::envelope('<Query />'), true];
        yield 'command & query: process (command)' => ['Process', self::envelope('<Command />'), false];
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

    public function testBlocksUnconfirmedCommandOfCommandQueryService(): void
    {
        $envelope = CommandQueryEnvelope::command(
            'PeriodService',
            new Command('ClosePeriod', ['PeriodNumber' => 1, 'Year' => 2021]),
            new AuthenticationHeader('x', '1002'),
        );
        $request = $envelope->toRequest('https://example.test/webservices/PeriodService.svc', new Psr17SoapRequestFactory(new Psr17Factory(), new Psr17Factory()));

        $this->expectException(WriteBlockedException::class);
        $this->expectExceptionMessage('Blocked SOAP write "Process" on company "1002": writes not confirmed.');

        $this->guard(allowWrites: null)->sendRequest($request);
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
