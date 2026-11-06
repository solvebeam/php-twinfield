<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\OAuth2;

final class AccessTokenValidation
{
    public function __construct(
        public readonly int $exp,
        public readonly string $organisationCode,
        public readonly string $organisationId,
        public readonly string $organisationUserCode,
        public readonly string $clusterUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            exp: (int) ($data['exp'] ?? 0),
            organisationCode: (string) ($data['twf.organisationCode'] ?? ''),
            organisationId: (string) ($data['twf.organisationId'] ?? ''),
            organisationUserCode: (string) ($data['twf.organisationUserCode'] ?? ''),
            clusterUrl: (string) ($data['twf.clusterUrl'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'exp' => $this->exp,
            'twf.organisationCode' => $this->organisationCode,
            'twf.organisationId' => $this->organisationId,
            'twf.organisationUserCode' => $this->organisationUserCode,
            'twf.clusterUrl' => $this->clusterUrl,
        ];
    }
}
