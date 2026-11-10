<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Soap;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SolveBeam\Twinfield\Authentication\AuthenticationHeader;
use SolveBeam\Twinfield\Finder\Search;
use SolveBeam\Twinfield\Finder\SearchResponse;
use SolveBeam\Twinfield\Soap\AccessDeniedException;
use SolveBeam\Twinfield\Soap\ClassicServiceFaultException;
use SolveBeam\Twinfield\Soap\ErrorCode;
use SolveBeam\Twinfield\Soap\Psr17SoapRequestFactory;
use SolveBeam\Twinfield\Soap\SoapClient;
use SolveBeam\Twinfield\Soap\RateLimitExceededException;
use SolveBeam\Twinfield\Soap\SoapFaultException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;

/**
 * Full round trip through the SOAP layer on recorded fixtures, without network.
 */
final class SoapClientTest extends TestCase
{
    public function testMapsFinderSearchResponse(): void
    {
        $http = ReplayClient::fixture('finder-search-dim');

        $response = $this->client($http)
            ->send('https://example.test/webservices/finder.asmx', new Search('DIM', '*', 0, 1, 10), $this->header())
            ->mapTo(SearchResponse::class);

        self::assertInstanceOf(SearchResponse::class, $response);
        self::assertNotNull($response->data);
        self::assertSame(2, $response->data->totalRows);
        self::assertSame(['Code', 'Naam'], $response->data->columns?->items);

        $rows = array_map(static fn ($row): array => $row->items, $response->data->items->items ?? []);

        self::assertSame([['1000', 'Voorbeeld BV'], ['1010', 'Test & Co']], $rows);
        self::assertSame('http://www.twinfield.com/Search', $http->lastRequest()->getHeaderLine('SOAPAction'));
    }

    public function testThrowsOnSoapFaultWithoutDetail(): void
    {
        try {
            $this->client(ReplayClient::fixture('fault-soap11', 500))
                ->send(
                    'https://example.test/webservices/finder.asmx',
                    new Search('DIM', '*', 0, 1, 10),
                    $this->header(),
                );
            self::fail('Expected a SoapFaultException.');
        } catch (SoapFaultException $exception) {
            self::assertSame(SoapFaultException::class, $exception::class);
            self::assertSame('soap:Client: Access denied. Token invalid.', $exception->getMessage());
            self::assertSame('soap:Client', $exception->faultCode);
        }
    }

    public function testThrowsSoapFaultWithGuidelinesDetail(): void
    {
        try {
            $this->client(ReplayClient::fixture('fault-guidelines-detail', 500))
                ->send(
                    'https://example.test/webservices/finder.asmx',
                    new Search('DIM', '*', 0, 1, 10),
                    $this->header(),
                );
            self::fail('Expected a ClassicServiceFaultException.');
        } catch (ClassicServiceFaultException $exception) {
            self::assertSame(
                'soap:Client: Access denied. Token invalid. (code 354, source Search)',
                $exception->getMessage(),
            );
            self::assertSame('354', $exception->errorCode);
            self::assertSame('Access denied. Token invalid.', $exception->errorMessage);
            self::assertSame('Search', $exception->source);
            self::assertSame(ErrorCode::TokenInvalid, ErrorCode::tryFrom($exception->errorCode));
        }
    }

    public function testThrowsSoapFaultOnUnauthorizedWithFault(): void
    {
        try {
            $this->client(ReplayClient::fixture('fault-guidelines-detail', 401))
                ->send(
                    'https://example.test/webservices/finder.asmx',
                    new Search('DIM', '*', 0, 1, 10),
                    $this->header(),
                );
            self::fail('Expected a ClassicServiceFaultException.');
        } catch (ClassicServiceFaultException $exception) {
            self::assertSame(ErrorCode::TokenInvalid, ErrorCode::tryFrom($exception->errorCode));
        }
    }

    public function testThrowsAccessDeniedOnUnauthorizedWithoutFault(): void
    {
        $http = new ReplayClient(new Response(401, ['WWW-Authenticate' => 'Bearer error="invalid_token"'], ''));

        try {
            $this->client($http)->send(
                'https://example.test/webservices/finder.asmx',
                new Search('DIM', '*', 0, 1, 10),
                $this->header(),
            );
            self::fail('Expected an AccessDeniedException.');
        } catch (AccessDeniedException $exception) {
            self::assertSame(401, $exception->getCode());
            self::assertSame(
                'Twinfield denied access (HTTP 401 Unauthorized). Bearer error="invalid_token"',
                $exception->getMessage(),
            );
        }
    }

    public function testThrowsAccessDeniedOnForbiddenWithoutFault(): void
    {
        $http = new ReplayClient(new Response(403, [], '<html>Forbidden</html>'));

        try {
            $this->client($http)->send(
                'https://example.test/webservices/finder.asmx',
                new Search('DIM', '*', 0, 1, 10),
                $this->header(),
            );
            self::fail('Expected an AccessDeniedException.');
        } catch (AccessDeniedException $exception) {
            self::assertSame(403, $exception->getCode());
            self::assertSame('Twinfield denied access (HTTP 403 Forbidden).', $exception->getMessage());
        }
    }

    public function testThrowsOnNonSoapResponse(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected SOAP XML response, received HTTP 502 Bad Gateway.');

        $this->client(new ReplayClient(new Response(502, [], '<html>Bad Gateway</html>')))
            ->send('https://example.test/webservices/finder.asmx', new Search('DIM', '*', 0, 1, 10), $this->header());
    }

    public function testThrowsOnRateLimit(): void
    {
        $http = new ReplayClient(new Response(429, [
            'Retry-After' => '30',
            'X-RateLimit-Limit' => '1000',
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-ClientId-Limit' => '1000',
            'X-RateLimit-ClientId-Remaining' => '400',
            'X-RateLimit-Credited' => '1',
        ], 'Too Many Requests'));

        try {
            $this->client($http)->send(
                'https://example.test/webservices/finder.asmx',
                new Search('DIM', '*', 0, 1, 10),
                $this->header(),
            );
            self::fail('Expected a RateLimitExceededException.');
        } catch (RateLimitExceededException $exception) {
            self::assertSame(30, $exception->retryAfterSeconds);
            self::assertSame(1000, $exception->rateLimitLimit);
            self::assertSame(0, $exception->rateLimitRemaining);
            self::assertSame(1000, $exception->rateLimitClientIdLimit);
            self::assertSame(400, $exception->rateLimitClientIdRemaining);
            self::assertSame(1, $exception->rateLimitCredited);
            self::assertFalse($exception->concurrencyLimitExceeded);
            self::assertStringContainsString('Retry after 30 seconds', $exception->getMessage());
        }
    }

    public function testThrowsOnRateLimitWithoutHeaders(): void
    {
        $http = new ReplayClient(new Response(429, [], 'Too Many Requests'));

        try {
            $this->client($http)->send(
                'https://example.test/webservices/finder.asmx',
                new Search('DIM', '*', 0, 1, 10),
                $this->header(),
            );
            self::fail('Expected a RateLimitExceededException.');
        } catch (RateLimitExceededException $exception) {
            self::assertNull($exception->retryAfterSeconds);
            self::assertNull($exception->rateLimitLimit);
            self::assertNull($exception->rateLimitRemaining);
            self::assertNull($exception->rateLimitClientIdLimit);
            self::assertNull($exception->rateLimitClientIdRemaining);
            self::assertNull($exception->rateLimitCredited);
            self::assertFalse($exception->concurrencyLimitExceeded);
            self::assertStringContainsString('Wait a moment and try again', $exception->getMessage());
        }
    }

    public function testThrowsOnConcurrencyLimit(): void
    {
        $http = new ReplayClient(new Response(429, [], 'Concurrency limit exceeded.'));

        try {
            $this->client($http)->send(
                'https://example.test/webservices/finder.asmx',
                new Search('DIM', '*', 0, 1, 10),
                $this->header(),
            );
            self::fail('Expected a RateLimitExceededException.');
        } catch (RateLimitExceededException $exception) {
            self::assertTrue($exception->concurrencyLimitExceeded);
            self::assertNull($exception->retryAfterSeconds);
            self::assertStringContainsString('concurrency limit exceeded', $exception->getMessage());
        }
    }

    private function client(ReplayClient $http): SoapClient
    {
        $psr17 = new Psr17Factory();

        return new SoapClient($http, new Psr17SoapRequestFactory($psr17, $psr17));
    }

    private function header(): AuthenticationHeader
    {
        return new AuthenticationHeader(accessToken: 'token-123', companyCode: '1002');
    }
}
