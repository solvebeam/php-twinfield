<?php

namespace SolveBeam\Twinfield\Declarations\Type;

class Header
{
    /**
     * @var null | string
     */
    private ?string $SessionID = null;

    /**
     * @var null | string
     */
    private ?string $AccessToken = null;

    /**
     * @var null | string
     */
    private ?string $CompanyCode = null;

    /**
     * @var null | string
     */
    private ?string $CompanyId = null;

    /**
     * @return null | string
     */
    public function getSessionID(): ?string
    {
        return $this->SessionID;
    }

    /**
     * @param null | string $SessionID
     * @return static
     */
    public function withSessionID(?string $SessionID): static
    {
        $new = clone $this;
        $new->SessionID = $SessionID;

        return $new;
    }

    /**
     * @return null | string
     */
    public function getAccessToken(): ?string
    {
        return $this->AccessToken;
    }

    /**
     * @param null | string $AccessToken
     * @return static
     */
    public function withAccessToken(?string $AccessToken): static
    {
        $new = clone $this;
        $new->AccessToken = $AccessToken;

        return $new;
    }

    /**
     * @return null | string
     */
    public function getCompanyCode(): ?string
    {
        return $this->CompanyCode;
    }

    /**
     * @param null | string $CompanyCode
     * @return static
     */
    public function withCompanyCode(?string $CompanyCode): static
    {
        $new = clone $this;
        $new->CompanyCode = $CompanyCode;

        return $new;
    }

    /**
     * @return null | string
     */
    public function getCompanyId(): ?string
    {
        return $this->CompanyId;
    }

    /**
     * @param null | string $CompanyId
     * @return static
     */
    public function withCompanyId(?string $CompanyId): static
    {
        $new = clone $this;
        $new->CompanyId = $CompanyId;

        return $new;
    }
}

