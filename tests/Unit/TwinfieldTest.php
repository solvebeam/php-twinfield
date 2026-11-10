<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit;

use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use SolveBeam\Twinfield\Finder\Search;
use SolveBeam\Twinfield\Office\OfficeSummary;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\Rest\NotFoundException;
use SolveBeam\Twinfield\Soap\RateLimit;
use SolveBeam\Twinfield\Soap\RateLimitExceededException;
use SolveBeam\Twinfield\Soap\SoapFaultException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;

final class TwinfieldTest extends TestCase
{
    public function testCompanyReturnsNewClient(): void
    {
        $twinfield = $this->twinfield();
        $company = $twinfield->company('1002');

        self::assertNotSame($twinfield, $company);
        self::assertNull($twinfield->companyCode());
        self::assertSame('1002', $company->companyCode());
        self::assertSame('1002_3', $company->company('1002_3')->companyCode());
        self::assertSame('1002', $company->companyCode());
    }

    public function testKnowsTheOrganisationOfTheAccessToken(): void
    {
        $twinfield = $this->twinfield();

        self::assertEquals(TestTokens::organisation(), $twinfield->organisation());
        self::assertEquals(TestTokens::company('1002'), $twinfield->company('1002')->companyReference());
    }

    public function testCompanyReferenceNeedsACompany(): void
    {
        $this->expectException(LogicException::class);

        $this->twinfield()->companyReference();
    }

    public function testTakesTheCompanyOfAModel(): void
    {
        $twinfield = $this->twinfield();
        $office = new OfficeSummary(
            TestTokens::organisation(),
            '1001',
            'Example Trading B.V.',
            '7c9e6679-7425-40de-944b-e07fc1f90ae7',
            'NL',
            1,
            21,
        );

        self::assertSame('1002', $twinfield->company(TestTokens::company('1002'))->companyCode());
        self::assertSame('1001', $twinfield->company($office)->companyCode());
    }

    public function testRefusesACompanyOfAnotherOrganisation(): void
    {
        $other = new Organisation('7c9e6679-7425-40de-944b-e07fc1f90ae7', 'OTHER');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Company 1000 belongs to organisation OTHER, but the access token to organisation EXAMPLE.',
        );

        $this->twinfield()->company(new CompanyReference($other, '1000'));
    }

    public function testBuildsEndpointFromClusterUrl(): void
    {
        self::assertSame(
            'https://api.accounting.twinfield.com/webservices/finder.asmx',
            $this->twinfield('https://api.accounting.twinfield.com/')->endpoint('finder'),
        );
    }

    public function testDiscoversHttpImplementations(): void
    {
        $twinfield = Twinfield::create(TestTokens::provider('https://api.accounting.twinfield.com'));

        self::assertSame(
            'https://api.accounting.twinfield.com/webservices/finder.asmx',
            $twinfield->endpoint('finder'),
        );
    }

    public function testHasNoRateLimitBeforeTheFirstRequest(): void
    {
        self::assertNull($this->twinfield()->lastRateLimit());
    }

    public function testKeepsTheRateLimitOfASoapResponseOfACompanyClient(): void
    {
        $twinfield = $this->twinfieldWith(
            ReplayClient::response('finder-search-dim')
                ->withHeader('X-RateLimit-Limit', '500')
                ->withHeader('X-RateLimit-Remaining', '499')
                ->withHeader('X-RateLimit-ClientId-Limit', '1000')
                ->withHeader('X-RateLimit-ClientId-Remaining', '999')
                ->withHeader('X-RateLimit-Credited', '1'),
        );
        $company = $twinfield->company('1002');

        $company->call('finder', new Search('DIM', '*', 0, 1, 10));

        // The client of company() shares it with the client it came from, and the other way round.
        self::assertEquals(new RateLimit(500, 499, 1000, 999, 1), $twinfield->lastRateLimit());
        self::assertSame($twinfield->lastRateLimit(), $company->lastRateLimit());
        self::assertSame($twinfield->lastRateLimit(), $twinfield->withoutCompany()->lastRateLimit());
    }

    public function testKeepsTheRateLimitOfASoapFault(): void
    {
        $twinfield = $this->twinfieldWith(
            ReplayClient::response('fault-soap11', 500)
                ->withHeader('X-RateLimit-Remaining', '496')
                ->withHeader('X-RateLimit-Credited', '3'),
        );

        try {
            $twinfield->company('1002')->call('finder', new Search('DIM', '*', 0, 1, 10));
            self::fail('Expected a SoapFaultException.');
        } catch (SoapFaultException) {
            self::assertEquals(new RateLimit(remaining: 496, credited: 3), $twinfield->lastRateLimit());
        }
    }

    public function testKeepsTheRateLimitOfARestResponse(): void
    {
        $twinfield = $this->twinfieldWith(
            ReplayClient::raw(200, '{}', ['X-RateLimit-Remaining' => '499', 'X-RateLimit-Credited' => '1']),
        );

        $twinfield->rest('GET', '/organisations/{organisationId}/companies');

        self::assertEquals(new RateLimit(remaining: 499, credited: 1), $twinfield->lastRateLimit());
    }

    public function testKeepsTheRateLimitOfARefusedRestRequest(): void
    {
        $twinfield = $this->twinfieldWith(
            ReplayClient::raw(404, '', ['X-RateLimit-Remaining' => '498', 'X-RateLimit-Credited' => '1']),
        );

        try {
            $twinfield->rest('GET', '/organisations/{organisationId}/companies');
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException) {
            self::assertEquals(new RateLimit(remaining: 498, credited: 1), $twinfield->lastRateLimit());
        }
    }

    public function testTheLastRateLimitIsOfTheMostRecentSoapOrRestResponse(): void
    {
        $twinfield = $this->twinfieldWith(
            ReplayClient::response('finder-search-dim')->withHeader('X-RateLimit-Remaining', '499'),
            ReplayClient::raw(200, '{}', ['X-RateLimit-Remaining' => '496']),
            // Without the headers, e.g. the concurrency limit: the last known value stays.
            ReplayClient::raw(429, 'Concurrency limit exceeded.'),
        );

        $twinfield->company('1002')->call('finder', new Search('DIM', '*', 0, 1, 10));

        self::assertEquals(new RateLimit(remaining: 499), $twinfield->lastRateLimit());

        $twinfield->rest('GET', '/organisations/{organisationId}/companies');

        self::assertEquals(new RateLimit(remaining: 496), $twinfield->lastRateLimit());

        try {
            $twinfield->rest('GET', '/organisations/{organisationId}/companies');
            self::fail('Expected a RateLimitExceededException.');
        } catch (RateLimitExceededException $exception) {
            self::assertTrue($exception->concurrencyLimitExceeded);
            self::assertEquals(new RateLimit(remaining: 496), $twinfield->lastRateLimit());
        }
    }

    private function twinfieldWith(ResponseInterface ...$responses): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider(), new ReplayClient(...$responses), $psr17, $psr17);
    }

    private function twinfield(string $clusterUrl = 'https://example.test'): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider($clusterUrl), new ReplayClient(), $psr17, $psr17);
    }
}
