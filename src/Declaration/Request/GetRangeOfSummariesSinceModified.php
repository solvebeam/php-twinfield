<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use DateTimeInterface;
use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetRangeOfSummariesSinceModified (declarations.asmx). Fields in WSDL order.
 *
 * The date is sent with its UTC offset; Twinfield reads a time without one as UTC.
 *
 * @internal
 */
#[SoapName('GetRangeOfSummariesSinceModified')]
#[SoapAction(action: 'http://www.twinfield.com/GetRangeOfSummariesSinceModified')]
final readonly class GetRangeOfSummariesSinceModified
{
    #[SoapName('companyCode')]
    public string $companyCode;

    #[SoapName('startIndex')]
    public int $startIndex;

    #[SoapName('count')]
    public int $count;

    #[SoapName('modifiedSince')]
    public string $modifiedSince;

    public function __construct(
        string $companyCode,
        int $startIndex,
        int $count,
        DateTimeInterface $modifiedSince,
    ) {
        $this->companyCode = $companyCode;
        $this->startIndex = $startIndex;
        $this->count = $count;
        $this->modifiedSince = $modifiedSince->format('Y-m-d\TH:i:sP');
    }
}
