<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support;

use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;

/**
 * Access token provider and organisation for offline tests, with fictitious values.
 */
final class TestTokens
{
    public const ORGANISATION_ID = '0b6a5f6e-1111-2222-3333-444455556666';
    public const ORGANISATION_CODE = 'EXAMPLE';

    public static function provider(
        string $clusterUrl = 'https://example.test',
        string $userCode = 'JDOE',
    ): StaticAccessTokenProvider {
        return new StaticAccessTokenProvider('token-123', self::organisation(), $userCode, $clusterUrl);
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
