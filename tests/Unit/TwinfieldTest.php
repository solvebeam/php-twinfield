<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit;

use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Office\OfficeSummary;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
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
        $office = new OfficeSummary(TestTokens::organisation(), '1001', 'Example Trading B.V.', 'id', 'NL', 1, 21);

        self::assertSame('1002', $twinfield->company(TestTokens::company('1002'))->companyCode());
        self::assertSame('1001', $twinfield->company($office)->companyCode());
    }

    public function testRefusesACompanyOfAnotherOrganisation(): void
    {
        $other = new Organisation('7c9e6679-7425-40de-944b-e07fc1f90ae7', 'OTHER');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Company 1000 belongs to organisation OTHER, but the access token to organisation EXAMPLE.');

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

        self::assertSame('https://api.accounting.twinfield.com/webservices/finder.asmx', $twinfield->endpoint('finder'));
    }

    private function twinfield(string $clusterUrl = 'https://example.test'): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider($clusterUrl), new ReplayClient(), $psr17, $psr17);
    }
}
