<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Office;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Office\Office;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class OfficeResourceTest extends TestCase
{
    public function testListsOfficesOverAllPages(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-offices-page-1'),
            ReplayClient::response('finder-offices-page-2'),
        );

        // The default page size (100) is larger than this fixture, but TotalRows says 3,
        // so the resource keeps fetching until it has all of them.
        $offices = $this->twinfield($http)->offices()->list();

        self::assertEquals([
            new Office('1000', 'Example Holding B.V.'),
            new Office('1001', 'Example Retail B.V.'),
            new Office('1002', 'Example Services B.V.'),
        ], $offices);
    }

    public function testFindsOneOffice(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-offices-page-1'),
            ReplayClient::response('finder-offices-page-2'),
        );

        self::assertEquals(new Office('1001', 'Example Retail B.V.'), $this->twinfield($http)->offices()->find('1001'));
    }

    public function testThrowsWhenARowHasNoName(): void
    {
        $http = new ReplayClient(ReplayClient::response('finder-offices-missing-name'));

        $this->expectException(UnexpectedResponseException::class);

        $this->twinfield($http)->offices()->list();
    }

    public function testOfficeRejectsEmptyCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Office('', 'Example Holding B.V.');
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(new StaticAccessTokenProvider('token-123', 'https://example.test'), $http, $psr17, $psr17);
    }
}
