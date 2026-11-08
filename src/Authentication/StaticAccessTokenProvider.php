<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Authentication;

use InvalidArgumentException;
use LogicException;

/**
 * Uses an access token you already have. It is not refreshed: once it
 * expires (after about an hour), calls fail.
 *
 *   $tokens = new StaticAccessTokenProvider($accessToken, $validation->clusterUrl, $validation->organisationUserCode);
 *
 * The user code is only needed for `$twinfield->userCode()` and `$twinfield->users()->current()`;
 * get it from the access token validation (see examples/getting-started/token-info).
 */
final readonly class StaticAccessTokenProvider implements AccessTokenProvider
{
    public function __construct(
        private string $accessToken,
        private string $clusterUrl = 'https://api.accounting.twinfield.com',
        private ?string $userCode = null,
    ) {
        if ($userCode === '') {
            throw new InvalidArgumentException('User code must not be empty; leave it out when unknown.');
        }
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function clusterUrl(): string
    {
        return rtrim($this->clusterUrl, '/');
    }

    /**
     * @throws LogicException When no user code was given.
     */
    public function userCode(): string
    {
        return $this->userCode ?? throw new LogicException(
            'The user of the access token is unknown: pass userCode to StaticAccessTokenProvider, '
            . 'e.g. $validation->organisationUserCode of the access token validation.',
        );
    }
}
