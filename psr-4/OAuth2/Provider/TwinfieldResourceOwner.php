<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\OAuth2\Provider;

use InvalidArgumentException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;

final class TwinfieldResourceOwner implements ResourceOwnerInterface
{
    public readonly string $sub;
    public readonly string $organisationUserCode;
    public readonly string $organisationId;

    /** @var array<string, mixed> */
    private array $response;

    /**
     * @param array<string, mixed> $response
     */
    public function __construct(array $response)
    {
        $sub = $response['sub'] ?? null;
        $organisationUserCode = $response['twf.organisationUserCode'] ?? null;
        $organisationId = $response['twf.organisationId'] ?? null;

        if (!is_string($sub) || $sub == '') {
            throw new InvalidArgumentException('Missing or invalid "sub" in resource owner response.');
        }

        if (!is_string($organisationUserCode) || $organisationUserCode == '') {
            throw new InvalidArgumentException('Missing or invalid "twf.organisationUserCode" in resource owner response.');
        }

        if (!is_string($organisationId) || $organisationId == '') {
            throw new InvalidArgumentException('Missing or invalid "twf.organisationId" in resource owner response.');
        }

        $this->sub = $sub;
        $this->organisationUserCode = $organisationUserCode;
        $this->organisationId = $organisationId;
        $this->response = $response;
    }

    public function getId(): ?string
    {
        return $this->sub;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->response;
    }
}
