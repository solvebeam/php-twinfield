<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\OAuth2;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\OAuth2\AccessTokenValidation;
use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class AccessTokenValidationTest extends TestCase
{
    /**
     * Claims of a live validation response (values fictitious); other claims are ignored.
     *
     * @var array<string, mixed>
     */
    private const array RESPONSE = [
        'exp' => 1794165191,
        'scope' => ['openid', 'twf.user', 'twf.organisationUser', 'twf.organisation'],
        'twf.organisationUserCode' => 'JDOE',
        'twf.organisationId' => '0b6a5f6e-1111-2222-3333-444455556666',
        'twf.organisationCode' => 'EXAMPLE',
        'twf.clusterUrl' => 'https://api.accounting.twinfield.com',
    ];

    public function testReadsTheClaims(): void
    {
        $validation = AccessTokenValidation::fromArray(self::RESPONSE);

        self::assertSame(1794165191, $validation->exp);
        self::assertSame('JDOE', $validation->organisationUserCode);
        self::assertSame('EXAMPLE', $validation->organisation->code);
        self::assertSame('0b6a5f6e-1111-2222-3333-444455556666', $validation->organisation->id);
        self::assertSame('https://api.accounting.twinfield.com', $validation->clusterUrl);
        self::assertSame(['openid', 'twf.user', 'twf.organisationUser', 'twf.organisation'], $validation->scopes);
        self::assertTrue($validation->hasScope(Scope::OrganisationUser));
        self::assertFalse($validation->hasScope(Scope::ReadVat));
        self::assertEquals($validation, AccessTokenValidation::fromArray($validation->toArray()));
    }

    public function testReadsASingleScopeAsString(): void
    {
        $validation = AccessTokenValidation::fromArray(['scope' => 'openid readVat'] + self::RESPONSE);

        self::assertSame(['openid', 'readVat'], $validation->scopes);
        self::assertTrue($validation->hasScope('readVat'));
    }

    public function testListsTheScopes(): void
    {
        self::assertSame(
            ['openid', 'twf.user', 'twf.organisation', 'twf.organisationUser', 'offline_access'],
            Scope::values(...Scope::identity()),
        );
        self::assertContains(Scope::ReadFixedAssets, Scope::rest());
        self::assertNotContains(Scope::OpenId, Scope::rest());
        self::assertCount(14, Scope::rest());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function claims(): iterable
    {
        yield 'exp' => ['exp', 'no "exp"'];
        yield 'user code' => ['twf.organisationUserCode', 'no "twf.organisationUserCode"'];
        yield 'organisation code' => ['twf.organisationCode', 'no "twf.organisationCode"'];
        yield 'organisation ID' => ['twf.organisationId', 'no "twf.organisationId"'];
        yield 'cluster URL' => ['twf.clusterUrl', 'no "twf.clusterUrl"'];
        yield 'scope' => ['scope', 'no "scope"'];
    }

    #[DataProvider('claims')]
    public function testThrowsOnAMissingClaim(string $claim, string $message): void
    {
        $response = self::RESPONSE;
        unset($response[$claim]);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($message);

        AccessTokenValidation::fromArray($response);
    }
}
