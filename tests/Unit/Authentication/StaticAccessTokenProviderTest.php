<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Authentication;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;

final class StaticAccessTokenProviderTest extends TestCase
{
    public function testReturnsWhatItWasGiven(): void
    {
        $tokens = new StaticAccessTokenProvider('token', 'https://example.test/', 'JDOE');

        self::assertSame('token', $tokens->accessToken());
        self::assertSame('https://example.test', $tokens->clusterUrl());
        self::assertSame('JDOE', $tokens->userCode());
    }

    public function testUserCodeIsOptional(): void
    {
        $tokens = new StaticAccessTokenProvider('token');

        self::assertSame('https://api.accounting.twinfield.com', $tokens->clusterUrl());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('pass userCode to StaticAccessTokenProvider');

        $tokens->userCode();
    }

    public function testRefusesAnEmptyUserCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StaticAccessTokenProvider('token', userCode: '');
    }
}
