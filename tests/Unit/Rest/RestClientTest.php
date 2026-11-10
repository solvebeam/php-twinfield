<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Rest;

use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Rest\NotFoundException;
use SolveBeam\Twinfield\Rest\RestException;
use SolveBeam\Twinfield\Rest\ValidationException;
use SolveBeam\Twinfield\Rest\VersionConflictException;
use SolveBeam\Twinfield\Soap\AccessDeniedException;
use SolveBeam\Twinfield\Soap\RateLimitExceededException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Twinfield;

final class RestClientTest extends TestCase
{
    private const COMPANY_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    public function testSendsAnAuthenticatedRequestWithTheGuidsInThePath(): void
    {
        $http = new ReplayClient(ReplayClient::raw(200, '{"ok": true}'));

        $response = $this->twinfield($http)->rest(
            'GET',
            '/organisations/{organisationId}/companies/{companyId}/sales/articles',
            ['searchPattern' => 'bike*', 'asc' => true, 'vats' => ['a', 'b'], 'limit' => 10, 'offset' => null],
        );

        $request = $http->lastRequest();

        self::assertTrue($response->json()->bool('ok'));
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            'https://example.test/Api/organisations/' . TestTokens::ORGANISATION_ID . '/companies/' . self::COMPANY_ID
            . '/sales/articles?searchPattern=bike%2A&asc=true&vats=a&vats=b&limit=10',
            (string) $request->getUri(),
        );
        self::assertSame('Bearer token-123', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('', $request->getHeaderLine('Content-Type'));
    }

    public function testSendsAJsonBodyWithExactAmounts(): void
    {
        $http = new ReplayClient(ReplayClient::raw(201, '{}'));

        $this->twinfield($http)->rest(
            'PUT',
            '/organisations/{organisationId}/companies/{companyId}/sales/articles/1',
            body: ['netPrice' => Amount::of('10')],
            headers: ['If-Match' => '3'],
        );

        $request = $http->lastRequest();

        self::assertSame('{"netPrice":10.00}', (string) $request->getBody());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('3', $request->getHeaderLine('If-Match'));
    }

    public function testLooksUpTheCompanyGuidOnceWithTheFinder(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-offices-with-id-page-1'),
            ReplayClient::response('finder-offices-with-id-page-2'),
            ReplayClient::raw(200, '{}'),
            ReplayClient::raw(200, '{}'),
        );
        $twinfield = Twinfield::create(TestTokens::provider(), $http, new Psr17Factory(), new Psr17Factory());

        self::assertSame('7c9e6679-7425-40de-944b-e07fc1f90ae7', $twinfield->company('1001')->companyId());

        $twinfield->company('1002')->rest('GET', '/organisations/{organisationId}/companies/{companyId}/vat/vatcodes');
        $twinfield->company('1000')->rest('GET', '/organisations/{organisationId}/companies/{companyId}/vat/vatcodes');

        self::assertCount(4, $http->requests);
        self::assertStringContainsString('/companies/f47ac10b-58cc-4372-a567-0e02b2c3d479/', (string) $http->requests[2]->getUri());
        self::assertStringContainsString('/companies/0f8fad5b-d9cb-469f-a165-70867728950e/', (string) $http->requests[3]->getUri());
    }

    public function testThrowsWhenTheCompanyIsNotAnOfficeOfTheUser(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-offices-with-id-page-1'),
            ReplayClient::response('finder-offices-with-id-page-2'),
        );
        $twinfield = Twinfield::create(TestTokens::provider(), $http, new Psr17Factory(), new Psr17Factory());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Company 9999 is not in the offices of the user, so its GUID is unknown.');

        $twinfield->company('9999')->companyId();
    }

    public function testRefusesACompanyIdThatIsNotAGuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->twinfield(new ReplayClient())->company('1000', '1000');
    }

    public function testRefusesAnUnknownPlaceholder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path /sales/articles/{id} has an unknown placeholder {id}.');

        $this->twinfield(new ReplayClient())->rest('GET', '/sales/articles/{id}');
    }

    public function testThrowsNotFound(): void
    {
        $http = new ReplayClient(ReplayClient::raw(404, '{"message": "Article with specified ID does not exist"}'));

        try {
            $this->twinfield($http)->rest('GET', '/organisations/{organisationId}/companies/{companyId}/sales/articles/x');
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $exception) {
            self::assertSame(404, $exception->status);
            self::assertSame(
                'Twinfield refused GET /sales/articles/x (HTTP 404 Not Found): Article with specified ID does not exist',
                $exception->getMessage(),
            );
        }
    }

    public function testThrowsAVersionConflict(): void
    {
        $this->expectException(VersionConflictException::class);

        $this->twinfield(new ReplayClient(ReplayClient::raw(412)))->rest('PUT', '/organisations/{organisationId}/companies/{companyId}/sales/articles/x', body: []);
    }

    public function testThrowsAValidationErrorWithTheMessages(): void
    {
        $body = '{"message": "Validation failed", "errors": [{"field": "code", "message": "Article code is already in use"}]}';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('(HTTP 422 Unprocessable Entity): Validation failed Article code is already in use');

        $this->twinfield(new ReplayClient(ReplayClient::raw(422, $body)))->rest('POST', '/organisations/{organisationId}/companies/{companyId}/sales/articles', body: []);
    }

    public function testThrowsOtherErrorsAsRestException(): void
    {
        try {
            $this->twinfield(new ReplayClient(ReplayClient::raw(500, 'Internal error')))->rest('GET', '/organisations/{organisationId}/budgets');
            self::fail('Expected a RestException.');
        } catch (RestException $exception) {
            self::assertSame(RestException::class, $exception::class);
            self::assertSame('Internal error', $exception->body);
        }
    }

    public function testThrowsAccessDeniedWithAHintAboutScopes(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Twinfield denied access (HTTP 403 Forbidden). GET /vat/vatcodes: request the scope of the operation');

        $this->twinfield(new ReplayClient(ReplayClient::raw(403)))->rest('GET', '/organisations/{organisationId}/companies/{companyId}/vat/vatcodes');
    }

    public function testThrowsWhenTheRateLimitIsExceeded(): void
    {
        $this->expectException(RateLimitExceededException::class);

        $this->twinfield(new ReplayClient(ReplayClient::raw(429, '', ['Retry-After' => '10'])))->rest('GET', '/organisations/{organisationId}/budgets');
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider(), $http, $psr17, $psr17)->company('1000', self::COMPANY_ID);
    }
}
