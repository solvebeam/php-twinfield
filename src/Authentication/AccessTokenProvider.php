<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Authentication;

/**
 * Supplies a valid access token, the cluster URL of the organisation and the user the token
 * belongs to.
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

    /**
     * Code of the Twinfield user the access token belongs to (`twf.organisationUserCode` of the
     * access token validation), e.g. `JDOE`. `$twinfield->users()->current()` reads this user.
     */
    public function userCode(): string;
}
