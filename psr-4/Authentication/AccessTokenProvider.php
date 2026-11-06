<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Authentication;

/**
 * Supplies a valid access token and the cluster URL of the organisation.
 *
 * Implementations:
 * - StaticAccessTokenProvider: a token you already have.
 * - RefreshTokenAccessTokenProvider: refreshes automatically with an OAuth refresh token.
 */
interface AccessTokenProvider
{
    public function accessToken(): string;

    /**
     * Base URL of the Twinfield cluster, e.g. `https://api.accounting.twinfield.com`.
     */
    public function clusterUrl(): string;
}
