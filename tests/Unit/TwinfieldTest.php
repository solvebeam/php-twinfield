<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
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

    public function testBuildsEndpointFromClusterUrl(): void
    {
        self::assertSame(
            'https://api.accounting.twinfield.com/webservices/finder.asmx',
            $this->twinfield('https://api.accounting.twinfield.com/')->endpoint('finder'),
        );
    }

    public function testDiscoversHttpImplementations(): void
    {
        $twinfield = Twinfield::create(new StaticAccessTokenProvider('token-123'));

        self::assertSame('https://api.accounting.twinfield.com/webservices/finder.asmx', $twinfield->endpoint('finder'));
    }

    private function twinfield(string $clusterUrl = 'https://example.test'): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(new StaticAccessTokenProvider('token-123', $clusterUrl), new ReplayClient(), $psr17, $psr17);
    }
}
