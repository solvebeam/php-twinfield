<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\OAuth2;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\OAuth2\Provider\Twinfield as TwinfieldProvider;
use SolveBeam\Twinfield\OAuth2\RefreshTokenAccessTokenProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RefreshTokenAccessTokenProviderTest extends TestCase
{
    public function testRefreshesOnceAndKeepsTokenInMemory(): void
    {
        $mock = $this->mock();
        $tokens = new RefreshTokenAccessTokenProvider($this->provider($mock), 'refresh-1');

        self::assertSame('access-1', $tokens->accessToken());
        self::assertSame('https://api.accounting.twinfield.com', $tokens->clusterUrl());
        self::assertSame('JDOE', $tokens->userCode(), 'The user code comes from the same validation.');
        self::assertSame('EXAMPLE', $tokens->organisation()->code, 'The organisation comes from the same validation.');
        self::assertSame('0b6a5f6e-1111-2222-3333-444455556666', $tokens->organisation()->id);
        self::assertSame('access-1', $tokens->accessToken());
        self::assertSame(0, $mock->count(), 'Token and validation requests were both used, and only once.');
        self::assertSame('refresh-2', $tokens->refreshToken(), 'A new refresh token from Twinfield is kept.');
    }

    public function testSharesAccessTokenViaCache(): void
    {
        $cache = new ArrayAdapter();

        (new RefreshTokenAccessTokenProvider($this->provider($this->mock()), 'refresh-1', $cache))->accessToken();

        $emptyMock = new MockHandler([]);
        $second = new RefreshTokenAccessTokenProvider($this->provider($emptyMock), 'refresh-1', $cache);

        self::assertSame('access-1', $second->accessToken(), 'Second instance uses the cached access token.');
        self::assertSame('JDOE', $second->userCode(), 'The user code is cached with the access token.');
        self::assertSame('EXAMPLE', $second->organisation()->code, 'The organisation is cached with the access token.');
    }

    public function testRefreshesWhenCachedTokenIsAboutToExpire(): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('twinfield.access_token.' . hash('sha256', 'refresh-1'));
        $item->set([
            'token' => 'old',
            'expires' => time() + 10,
            'clusterUrl' => 'https://example.test',
            'userCode' => 'JDOE',
            'organisationId' => '0b6a5f6e-1111-2222-3333-444455556666',
            'organisationCode' => 'EXAMPLE',
        ]);
        $cache->save($item);

        $tokens = new RefreshTokenAccessTokenProvider($this->provider($this->mock()), 'refresh-1', $cache);

        self::assertSame('access-1', $tokens->accessToken());
    }

    public function testRefreshesACachedTokenWithoutUserCode(): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('twinfield.access_token.' . hash('sha256', 'refresh-1'));
        // Cache entry of a version before userCode().
        $item->set(['token' => 'old', 'expires' => time() + 3600, 'clusterUrl' => 'https://example.test']);
        $cache->save($item);

        $tokens = new RefreshTokenAccessTokenProvider($this->provider($this->mock()), 'refresh-1', $cache);

        self::assertSame('JDOE', $tokens->userCode());
        self::assertSame('access-1', $tokens->accessToken());
    }

    public function testRefreshesACachedTokenWithoutOrganisation(): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('twinfield.access_token.' . hash('sha256', 'refresh-1'));
        // Cache entry of a version before organisation().
        $item->set(['token' => 'old', 'expires' => time() + 3600, 'clusterUrl' => 'https://example.test', 'userCode' => 'JDOE']);
        $cache->save($item);

        $tokens = new RefreshTokenAccessTokenProvider($this->provider($this->mock()), 'refresh-1', $cache);

        self::assertSame('EXAMPLE', $tokens->organisation()->code);
        self::assertSame('access-1', $tokens->accessToken());
    }

    private function mock(): MockHandler
    {
        return new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'access_token' => 'access-1',
                'refresh_token' => 'refresh-2',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ])),
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'exp' => time() + 3600,
                'twf.clusterUrl' => 'https://api.accounting.twinfield.com/',
                'twf.organisationCode' => 'EXAMPLE',
                'twf.organisationId' => '0b6a5f6e-1111-2222-3333-444455556666',
                'twf.organisationUserCode' => 'JDOE',
            ])),
        ]);
    }

    private function provider(MockHandler $mock): TwinfieldProvider
    {
        return new TwinfieldProvider(
            ['clientId' => 'client', 'clientSecret' => 'secret', 'redirectUri' => 'http://localhost:8765/callback'],
            ['httpClient' => new Client(['handler' => HandlerStack::create($mock)])],
        );
    }
}
