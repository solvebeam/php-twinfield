<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetRangeOfSummariesByDeclarationYearAndSinceModified implements RequestInterface
{
    /**
     * @var null | string
     */
    private ?string $companyCode = null;

    /**
     * @var int
     */
    private int $declarationYear;

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
     * @param int $declarationYear
     * @param int $startIndex
     * @param int $count
     * @param \DateTimeInterface $modifiedSince
     */
    public function __construct(?string $companyCode, int $declarationYear, int $startIndex, int $count, \DateTimeInterface $modifiedSince)
    {
        $this->companyCode = $companyCode;
        $this->declarationYear = $declarationYear;
        $this->startIndex = $startIndex;
        $this->count = $count;
        $this->modifiedSince = $modifiedSince;
    }
}
