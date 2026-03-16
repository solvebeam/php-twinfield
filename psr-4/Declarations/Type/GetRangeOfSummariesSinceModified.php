<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetRangeOfSummariesSinceModified implements RequestInterface
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
     * @var \DateTimeInterface
     */
    private \DateTimeInterface $modifiedSince;

    /**
     * Constructor
     *
     * @param null | string $companyCode
     * @param int $startIndex
     * @param int $count
     * @param \DateTimeInterface $modifiedSince
     */
    public function __construct(?string $companyCode, int $startIndex, int $count, \DateTimeInterface $modifiedSince)
    {
        $this->companyCode = $companyCode;
        $this->startIndex = $startIndex;
        $this->count = $count;
        $this->modifiedSince = $modifiedSince;
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

    /**
     * @return int
     */
    public function getStartIndex(): int
    {
        return $this->startIndex;
    }

    /**
     * @param int $startIndex
     * @return static
     */
    public function withStartIndex(int $startIndex): static
    {
        $new = clone $this;
        $new->startIndex = $startIndex;

        return $new;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @param int $count
     * @return static
     */
    public function withCount(int $count): static
    {
        $new = clone $this;
        $new->count = $count;

        return $new;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getModifiedSince(): \DateTimeInterface
    {
        return $this->modifiedSince;
    }

    /**
     * @param \DateTimeInterface $modifiedSince
     * @return static
     */
    public function withModifiedSince(\DateTimeInterface $modifiedSince): static
    {
        $new = clone $this;
        $new->modifiedSince = $modifiedSince;

        return $new;
    }
}

