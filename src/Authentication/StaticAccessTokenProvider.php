<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Authentication;

/**
 * Uses an access token you already have. It is not refreshed: once it
 * expires (after about an hour), calls fail.
 */
final readonly class StaticAccessTokenProvider implements AccessTokenProvider
{
    public function __construct(
        private string $accessToken,
        private string $clusterUrl = 'https://api.accounting.twinfield.com',
    ) {
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function clusterUrl(): string
    {
        return rtrim($this->clusterUrl, '/');
    }
}
