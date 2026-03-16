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
}

