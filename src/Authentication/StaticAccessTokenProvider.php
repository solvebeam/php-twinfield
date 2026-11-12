<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Authentication;

use InvalidArgumentException;
use SolveBeam\Twinfield\OAuth2\AccessTokenValidation;
use SolveBeam\Twinfield\Organisation\Organisation;

/**
 * Uses an access token you already have. It is not refreshed: once it
 * expires (after about an hour), calls fail.
 *
 *   $validation = $provider->getAccessTokenValidation($token);
 *   $tokens = StaticAccessTokenProvider::fromValidation($accessToken, $validation);
 *
 * The access token validation gives the cluster URL, the organisation and the user of the
 * token; see examples/getting-started/token-info.
 */
final readonly class StaticAccessTokenProvider implements AccessTokenProvider
{
    public function __construct(
        private string $accessToken,
        private Organisation $organisation,
        /** Code of the user the token belongs to (`twf.organisationUserCode`), e.g. `JDOE`. */
        private string $userCode,
        private string $clusterUrl = 'https://api.accounting.twinfield.com',
    ) {
        if ($accessToken === '' || $userCode === '' || $clusterUrl === '') {
            throw new InvalidArgumentException('Access token, user code and cluster URL must not be empty.');
        }
    }

    /**
     * With the organisation, user and cluster URL of the access token validation.
     */
    public static function fromValidation(string $accessToken, AccessTokenValidation $validation): self
    {
        return new self(
            $accessToken,
            new Organisation($validation->organisationId, $validation->organisationCode),
            $validation->organisationUserCode,
            $validation->clusterUrl,
        );
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function clusterUrl(): string
    {
        return rtrim($this->clusterUrl, '/');
    }

    public function userCode(): string
    {
        return $this->userCode;
    }

    public function organisation(): Organisation
    {
        return $this->organisation;
    }
}
