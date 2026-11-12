<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Authentication;

use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;
use SolveBeam\Twinfield\OAuth2\Provider\AbstractTwinfieldProvider;
use SolveBeam\Twinfield\Organisation\Organisation;

/**
 * Gets access tokens with an OAuth refresh token and refreshes them when
 * they expire. Pass a PSR-6 cache to share access tokens between requests.
 *
 * The cluster URL, the organisation and the user code come from the access token validation,
 * which it calls once per new access token.
 *
 *   $tokens = new RefreshTokenAccessTokenProvider(
 *       new Twinfield(['clientId' => …, 'clientSecret' => …, 'redirectUri' => …]),
 *       refreshToken: $storedRefreshToken,
 *       cache: $cachePool,
 *   );
 *
 * The provider is the `Twinfield` provider of `solvebeam/oauth2-twinfield`, or another provider of
 * Twinfield access tokens that extends `AbstractTwinfieldProvider`.
 *
 * Twinfield may return a new refresh token on refresh; read it with
 * refreshToken() and store it if you persist refresh tokens yourself.
 */
final class RefreshTokenAccessTokenProvider implements AccessTokenProvider
{
    /** Refresh this many seconds before the access token expires. */
    private const int EXPIRY_MARGIN = 60;

    /** @var array{token: string, expires: int, clusterUrl: string, userCode: string, organisationId: string, organisationCode: string}|null */
    private ?array $current = null;

    public function __construct(
        private readonly AbstractTwinfieldProvider $provider,
        private string $refreshToken,
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
    }

    public function accessToken(): string
    {
        return $this->current()['token'];
    }

    public function clusterUrl(): string
    {
        return $this->current()['clusterUrl'];
    }

    public function userCode(): string
    {
        return $this->current()['userCode'];
    }

    public function organisation(): Organisation
    {
        $current = $this->current();

        return new Organisation($current['organisationId'], $current['organisationCode']);
    }

    public function refreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * @return array{
     *     token: string,
     *     expires: int,
     *     clusterUrl: string,
     *     userCode: string,
     *     organisationId: string,
     *     organisationCode: string,
     * }
     */
    private function current(): array
    {
        if ($this->current !== null && $this->isValid($this->current)) {
            return $this->current;
        }

        $cached = $this->cache?->getItem($this->cacheKey());
        $value = $cached?->get();

        if ($cached !== null && $cached->isHit() && is_array($value) && $this->isValid($value)) {
            /** @var array{token: string, expires: int, clusterUrl: string, userCode: string, organisationId: string, organisationCode: string} $value */
            return $this->current = $value;
        }

        $token = $this->provider->getAccessToken('refresh_token', ['refresh_token' => $this->refreshToken]);
        $validation = $this->provider->getAccessTokenValidation($token);

        $this->current = [
            'token' => $token->getToken(),
            'expires' => $token->getExpires() ?? $validation->exp,
            'clusterUrl' => rtrim($validation->clusterUrl, '/'),
            'userCode' => $validation->organisationUserCode,
            'organisationId' => $validation->organisationId,
            'organisationCode' => $validation->organisationCode,
        ];

        if ($cached !== null) {
            $cached->set($this->current);
            $cached->expiresAt(new DateTimeImmutable('@' . $this->current['expires']));
            $this->cache->save($cached);
        }

        $this->refreshToken = $token->getRefreshToken() ?? $this->refreshToken;

        return $this->current;
    }

    /**
     * @param array<mixed> $value
     */
    private function isValid(array $value): bool
    {
        return is_string($value['token'] ?? null)
            && is_string($value['clusterUrl'] ?? null)
            // Cache entries of older versions have no user code or organisation; they are refreshed.
            && is_string($value['userCode'] ?? null)
            && is_string($value['organisationId'] ?? null)
            && is_string($value['organisationCode'] ?? null)
            && is_int($value['expires'] ?? null)
            && $value['expires'] - self::EXPIRY_MARGIN > time();
    }

    private function cacheKey(): string
    {
        // PSR-6 keys allow A-Z, a-z, 0-9, _ and . only; never use the token itself.
        return 'twinfield.access_token.' . hash('sha256', $this->refreshToken);
    }
}
