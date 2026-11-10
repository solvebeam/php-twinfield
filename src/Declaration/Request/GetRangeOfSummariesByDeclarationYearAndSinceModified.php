<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use DateTimeInterface;
use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetRangeOfSummariesByDeclarationYearAndSinceModified (declarations.asmx). Fields in WSDL order.
 *
 * The date is sent with its UTC offset; Twinfield reads a time without one as UTC.
 *
 * @internal
 */
#[SoapName('GetRangeOfSummariesByDeclarationYearAndSinceModified')]
#[SoapAction(action: 'http://www.twinfield.com/GetRangeOfSummariesByDeclarationYearAndSinceModified')]
final readonly class GetRangeOfSummariesByDeclarationYearAndSinceModified
{
    #[SoapName('companyCode')]
    public string $companyCode;

    #[SoapName('declarationYear')]
    public int $declarationYear;

    #[SoapName('startIndex')]
    public int $startIndex;

    #[SoapName('count')]
    public int $count;

    #[SoapName('modifiedSince')]
    public string $modifiedSince;

    public function __construct(
        string $companyCode,
        int $declarationYear,
        int $startIndex,
        int $count,
        DateTimeInterface $modifiedSince,
    ) {
        $this->companyCode = $companyCode;
        $this->declarationYear = $declarationYear;
        $this->startIndex = $startIndex;
        $this->count = $count;
        $this->modifiedSince = $modifiedSince->format('Y-m-d\TH:i:sP');
    }
}
