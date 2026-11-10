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
    private const ORGANISATION = '0b6a5f6e-1111-2222-3333-444455556666';
    private const COMPANY_1000 = '0f8fad5b-d9cb-469f-a165-70867728950e';
    private const COMPANY_1002 = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

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
        yield 'hierarchy load' => ['Load', self::envelope('<Load />'), true];
        yield 'hierarchy save' => ['Save', self::envelope('<Save />'), false];
        yield 'hierarchy bind' => ['Bind', self::envelope('<Bind />'), false];
        yield 'hierarchy hide' => ['Hide', self::envelope('<Hide />'), false];
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

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function restRequests(): iterable
    {
        $company = '/Api/organisations/' . self::ORGANISATION . '/companies/' . self::COMPANY_1002;

        yield 'get' => ['GET', $company . '/sales/articles', true];
        yield 'options' => ['OPTIONS', $company . '/sales/articles', true];
        yield 'head' => ['HEAD', $company . '/sales/articles', true];
        yield 'post' => ['POST', $company . '/sales/articles', false];
        yield 'put' => ['PUT', $company . '/sales/articles/x', false];
        yield 'patch' => ['PATCH', $company . '/sales/articles/x', false];
        yield 'delete' => ['DELETE', $company . '/sales/articles/x', false];
        yield 'queue fixed asset report' => ['POST', $company . '/fixedassets/backgroundreports/reconciliation', true];
        yield 'import' => ['POST', $company . '/budgeting/budgets/import', false];
    }

    #[DataProvider('restRequests')]
    public function testClassifiesRestRequests(string $method, string $path, bool $readOnly): void
    {
        self::assertTrue(WriteGuardClient::isRest($path));
        self::assertSame($readOnly, WriteGuardClient::isRestReadOnly($method, $path));
    }

    public function testLetsRestReadsThrough(): void
    {
        $response = $this->guard(allowWrites: null)->sendRequest($this->restRequest('GET', self::COMPANY_1002));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testBlocksUnconfirmedRestWrites(): void
    {
        $this->expectException(WriteBlockedException::class);
        $this->expectExceptionMessage('Blocked REST write "POST /Api/organisations/');
        $this->expectExceptionMessage('on company "1002": writes not confirmed.');

        $this->guard(allowWrites: null)->sendRequest($this->restRequest('POST', self::COMPANY_1002));
    }

    public function testBlocksRestWritesOnAnotherCompany(): void
    {
        $this->expectException(WriteBlockedException::class);
        $this->expectExceptionMessage('on company "1000": not in TWINFIELD_WRITE_COMPANIES');

        $this->guard(allowWrites: '1002')->sendRequest($this->restRequest('DELETE', self::COMPANY_1000));
    }

    public function testBlocksRestWritesOnAnUnknownCompany(): void
    {
        $this->expectException(WriteBlockedException::class);
        $this->expectExceptionMessage('is not an office of the user');

        $this->guard(allowWrites: '1002')->sendRequest($this->restRequest('PUT', '7c9e6679-7425-40de-944b-e07fc1f90ae7'));
    }

    public function testBlocksRestWritesWithoutCompany(): void
    {
        $this->expectException(WriteBlockedException::class);
        $this->expectExceptionMessage('no company GUID found in the path');

        $this->guard(allowWrites: '1002')->sendRequest(new Request('POST', 'https://example.test/Api/organisations/' . self::ORGANISATION . '/x'));
    }

    public function testBlocksRestWritesWithoutResolver(): void
    {
        $guard = new WriteGuardClient(new ReplayClient(new Response(200)), ['1002'], '1002');

        $this->expectException(WriteBlockedException::class);

        $guard->sendRequest($this->restRequest('POST', self::COMPANY_1002));
    }

    public function testAllowsConfirmedRestWrites(): void
    {
        $response = $this->guard(allowWrites: '1002')->sendRequest($this->restRequest('PATCH', strtoupper(self::COMPANY_1002)));

        self::assertSame(200, $response->getStatusCode());
    }

    private function guard(?string $allowWrites): WriteGuardClient
    {
        $codes = [self::COMPANY_1000 => '1000', self::COMPANY_1002 => '1002'];

        return new WriteGuardClient(
            new ReplayClient(new Response(200)),
            ['1002', '1002_3'],
            $allowWrites,
            static fn (string $companyId): ?string => $codes[strtolower($companyId)] ?? null,
        );
    }

    private function restRequest(string $method, string $companyId): Request
    {
        return new Request(
            $method,
            'https://example.test/Api/organisations/' . self::ORGANISATION . '/companies/' . $companyId . '/sales/articles',
        );
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
