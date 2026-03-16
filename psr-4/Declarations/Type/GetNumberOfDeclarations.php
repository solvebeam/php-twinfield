<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetNumberOfDeclarations implements RequestInterface
{
    /**
     * @var null | string
     */
    private ?string $companyCode = null;

    /**
     * Constructor
     *
     * @param null | string $companyCode
     */
    public function __construct(?string $companyCode)
    {
        $this->companyCode = $companyCode;
    }

    /**
     * @return null | string
     */
    public function getCompanyCode(): ?string
    {
        return $this->companyCode;
    }

    /**
     * @param null | string $companyCode
     * @return static
     */
    public function withCompanyCode(?string $companyCode): static
    {
        $new = clone $this;
        $new->companyCode = $companyCode;

        return $new;
    }
}

