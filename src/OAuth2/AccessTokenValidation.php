<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\OAuth2;

use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Result of the access token validation endpoint: who the token belongs to and which cluster
 * API calls go to.
 *
 * The claims come from the scopes `twf.organisationUser` (mandatory to log in) and
 * `twf.organisation`.
 */
final readonly class AccessTokenValidation
{
    public function __construct(
        /** Expiry time of the access token, as a Unix timestamp. */
        public int $exp,
        /** The organisation of the token (`twf.organisationId` and `twf.organisationCode`). */
        public Organisation $organisation,
        /** Code of the Twinfield user the token belongs to, e.g. `JDOE`: `$twinfield->users()->get()` reads it. */
        public string $organisationUserCode,
        /** Base URL of the cluster, e.g. `https://api.accounting.twinfield.com`. */
        public string $clusterUrl,
        /**
         * The scopes the token has, e.g. `['openid', 'twf.organisationUser', 'readVat']`.
         *
         * @var list<string>
         */
        public array $scopes,
    ) {
        $required = [
            'organisation user code' => $organisationUserCode,
            'cluster URL' => $clusterUrl,
        ];

        foreach ($required as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Access token validation %s must not be empty.', $field));
            }
        }
    }

    /**
     * From the JSON of the validation endpoint.
     *
     * @param array<mixed> $data
     *
     * @throws UnexpectedResponseException When a claim is missing, e.g. because a scope was not requested.
     */
    public static function fromArray(array $data): self
    {
        $exp = $data['exp'] ?? null;

        if (!is_int($exp)) {
            throw new UnexpectedResponseException('Access token validation has no "exp" (expiry time).');
        }

        return new self(
            exp: $exp,
            organisation: new Organisation(
                id: self::claim($data, 'twf.organisationId'),
                code: self::claim($data, 'twf.organisationCode'),
            ),
            organisationUserCode: self::claim($data, 'twf.organisationUserCode'),
            clusterUrl: self::claim($data, 'twf.clusterUrl'),
            scopes: self::scopes($data),
        );
    }

    /**
     * True when the token has the scope, e.g. `hasScope(Scope::ReadVat)` before calling the REST API.
     */
    public function hasScope(Scope|string $scope): bool
    {
        return in_array($scope instanceof Scope ? $scope->value : $scope, $this->scopes, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'exp' => $this->exp,
            'twf.organisationCode' => $this->organisation->code,
            'twf.organisationId' => $this->organisation->id,
            'twf.organisationUserCode' => $this->organisationUserCode,
            'twf.clusterUrl' => $this->clusterUrl,
            'scope' => $this->scopes,
        ];
    }

    /**
     * `scope` is a JSON array, or a string for a single scope.
     *
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private static function scopes(array $data): array
    {
        $scope = $data['scope'] ?? null;
        $scopes = is_string($scope) ? explode(' ', $scope) : $scope;

        if (!is_array($scopes) || $scopes === [] || array_filter($scopes, 'is_string') !== $scopes) {
            throw new UnexpectedResponseException(
                'Access token validation has no "scope" with the scopes of the token.',
            );
        }

        return array_values(array_filter($scopes, static fn (string $scope): bool => $scope !== ''));
    }

    /**
     * @param array<mixed> $data
     */
    private static function claim(array $data, string $name): string
    {
        $value = $data[$name] ?? null;

        if (!is_string($value) || $value === '') {
            throw new UnexpectedResponseException(sprintf(
                'Access token validation has no "%s"; '
                    . 'request the scopes twf.organisationUser and twf.organisation at login.',
                $name,
            ));
        }

        return $value;
    }
}
