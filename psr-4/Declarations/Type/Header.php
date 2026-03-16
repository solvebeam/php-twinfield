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
     * @return null | string
     */
    public function getAccessToken(): ?string
    {
        return $this->AccessToken;
    }

    /**
     * @return null | string
     */
    public function getCompanyCode(): ?string
    {
        return $this->CompanyCode;
    }

    /**
     * @return null | string
     */
    public function getCompanyId(): ?string
    {
        return $this->CompanyId;
    }
}

