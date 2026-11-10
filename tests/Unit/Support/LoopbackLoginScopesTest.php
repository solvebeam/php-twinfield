<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\Tests\Support\Setup\LoopbackLogin;

final class LoopbackLoginScopesTest extends TestCase
{
    public function testRestScopesAreAllScopesOfTheSdk(): void
    {
        $expected = Scope::values(...Scope::cases());
        $actual = LoopbackLogin::REST_SCOPES;

        sort($expected);
        sort($actual);

        self::assertSame($expected, $actual);
    }
}
