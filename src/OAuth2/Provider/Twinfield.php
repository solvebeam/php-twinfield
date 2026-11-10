<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\OAuth2\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;
use SolveBeam\Twinfield\OAuth2\AccessTokenValidation;
use SolveBeam\Twinfield\UnexpectedResponseException;
use SolveBeam\Twinfield\OAuth2\Provider\TwinfieldResourceOwner;

final class Twinfield extends AbstractProvider
{
    use BearerAuthorizationTrait;

    private const AUTHORIZATION_URL = 'https://login.twinfield.com/auth/authentication/connect/authorize';
    private const ACCESS_TOKEN_URL = 'https://login.twinfield.com/auth/authentication/connect/token';
    private const RESOURCE_OWNER_URL = 'https://login.twinfield.com/auth/authentication/connect/userinfo';
    private const ACCESS_TOKEN_VALIDATION_URL =
        'https://login.twinfield.com/auth/authentication/connect/accesstokenvalidation';

    public function getBaseAuthorizationUrl(): string
    {
        return self::AUTHORIZATION_URL;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        return self::ACCESS_TOKEN_URL;
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return self::RESOURCE_OWNER_URL;
    }

    public function getAccessTokenValidation(AccessTokenInterface $token): AccessTokenValidation
    {
        $tokenValue = trim($token->getToken());

        if ($tokenValue === '') {
            throw new IdentityProviderException('Access token is missing', 0, ['error' => 'token_missing']);
        }

        $url = self::ACCESS_TOKEN_VALIDATION_URL . '?token=' . rawurlencode($tokenValue);
        $request = $this->getAuthenticatedRequest(self::METHOD_GET, $url, $token);
        $response = $this->getResponse($request);
        /**
         * League documents parseResponse() as returning an array, but it returns the body as a
         * string when it is not JSON.
         *
         * @var array<mixed>|string $data
         */
        $data = $this->parseResponse($response);

        $this->checkResponse($response, $data);

        if (!is_array($data)) {
            throw new UnexpectedResponseException('Access token validation did not return a JSON object.');
        }

        return AccessTokenValidation::fromArray($data);
    }

    /**
     * @return list<string>
     */
    protected function getDefaultScopes(): array
    {
        return [];
    }

    /**
     * Drops `approval_prompt`, a Google-specific parameter League adds by default.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function getAuthorizationParameters(array $options): array
    {
        $parameters = parent::getAuthorizationParameters($options);

        unset($parameters['approval_prompt']);

        return $parameters;
    }

    /**
     * OAuth 2.0 scopes are space-delimited (RFC 6749, section 3.3).
     */
    protected function getScopeSeparator(): string
    {
        return ' ';
    }

    /**
     * @param array<mixed>|string $data Parsed response data, or the body when it is not JSON.
     */
    protected function checkResponse(ResponseInterface $response, $data): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $message = $data['error_description'] ?? $data['message'] ?? $response->getReasonPhrase();
            $error = $data['error'] ?? (string) $statusCode;

            throw new IdentityProviderException($message, $statusCode, ['error' => $error]);
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    protected function createResourceOwner(array $response, AccessToken $token): TwinfieldResourceOwner
    {
        return new TwinfieldResourceOwner($response);
    }
}
