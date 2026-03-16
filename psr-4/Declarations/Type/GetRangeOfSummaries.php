<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetRangeOfSummaries implements RequestInterface
{
    /**
     * @var null | string
     */
    private ?string $companyCode = null;

    /**
     * @var int
     */
    private int $startIndex;

    /**
     * @var int
     */
    private int $count;

    /**
     * Constructor
     *
     * @param null | string $companyCode
     * @param int $startIndex
     * @param int $count
     */
    public function __construct(?string $companyCode, int $startIndex, int $count)
    {
        $this->companyCode = $companyCode;
        $this->startIndex = $startIndex;
        $this->count = $count;
    }

    /**
     * @return null | string
     */
    public function getCompanyCode(): ?string
    {
        return $this->companyCode;
    }

    /**
     * @return int
     */
    public function getStartIndex(): int
    {
        return $this->startIndex;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }
}

