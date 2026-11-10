<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Authentication;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\OAuth2\AccessTokenValidation;
use SolveBeam\Twinfield\Organisation\Organisation;

final class StaticAccessTokenProviderTest extends TestCase
{
    public function testReturnsWhatItWasGiven(): void
    {
        $organisation = new Organisation('0b6a5f6e-1111-2222-3333-444455556666', 'EXAMPLE');
        $tokens = new StaticAccessTokenProvider('token', $organisation, 'JDOE', 'https://example.test/');

        self::assertSame('token', $tokens->accessToken());
        self::assertSame('https://example.test', $tokens->clusterUrl());
        self::assertSame('JDOE', $tokens->userCode());
        self::assertSame($organisation, $tokens->organisation());
    }

    public function testUsesTheDefaultClusterUrl(): void
    {
        $tokens = new StaticAccessTokenProvider('token', new Organisation('0b6a', 'EXAMPLE'), 'JDOE');

        self::assertSame('https://api.accounting.twinfield.com', $tokens->clusterUrl());
    }

    public function testTakesEverythingFromTheValidation(): void
    {
        $validation = AccessTokenValidation::fromArray([
            'exp' => 1794165191,
            'twf.organisationUserCode' => 'JDOE',
            'twf.organisationId' => '0b6a5f6e-1111-2222-3333-444455556666',
            'twf.organisationCode' => 'EXAMPLE',
            'twf.clusterUrl' => 'https://api.accounting.twinfield.com/',
            'scope' => ['openid', 'twf.organisationUser'],
        ]);

        $tokens = StaticAccessTokenProvider::fromValidation('token', $validation);

        self::assertSame('https://api.accounting.twinfield.com', $tokens->clusterUrl());
        self::assertSame('JDOE', $tokens->userCode());
        self::assertSame('EXAMPLE', $tokens->organisation()->code);
        self::assertSame('0b6a5f6e-1111-2222-3333-444455556666', $tokens->organisation()->id);
    }

    public function testRefusesAnEmptyUserCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StaticAccessTokenProvider('token', new Organisation('0b6a', 'EXAMPLE'), '');
    }
}
