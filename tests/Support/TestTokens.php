<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\Twinfield;

/**
 * Access token provider and organisation for offline tests, with fictitious values.
 */
final class TestTokens
{
    public const ORGANISATION_ID = '0b6a5f6e-1111-2222-3333-444455556666';
    public const ORGANISATION_CODE = 'EXAMPLE';

    /** GUID of company `1000` in REST API tests. */
    public const COMPANY_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    /** Start of the REST API paths of company `1000`. */
    public const REST_COMPANY_URL = 'https://example.test/Api/organisations/' . self::ORGANISATION_ID
        . '/companies/' . self::COMPANY_ID;

    public static function provider(
        string $clusterUrl = 'https://example.test',
        string $userCode = 'JDOE',
    ): StaticAccessTokenProvider {
        return new StaticAccessTokenProvider('token-123', self::organisation(), $userCode, $clusterUrl);
    }

    /**
     * Client for company `1000` with its GUID, for REST API tests: no Finder lookup.
     */
    public static function restClient(ClientInterface $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(self::provider(), $http, $psr17, $psr17)->company('1000', self::COMPANY_ID);
    }

    public static function organisation(): Organisation
    {
        return new Organisation(self::ORGANISATION_ID, self::ORGANISATION_CODE);
    }

    public static function company(string $code = '1000'): CompanyReference
    {
        return new CompanyReference(self::organisation(), $code);
    }
}
