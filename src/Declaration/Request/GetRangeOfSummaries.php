<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetRangeOfSummaries (declarations.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('GetRangeOfSummaries')]
#[SoapAction(action: 'http://www.twinfield.com/GetRangeOfSummaries')]
final readonly class GetRangeOfSummaries
{
    public function __construct(
        #[SoapName('companyCode')]
        public string $companyCode,
        #[SoapName('startIndex')]
        public int $startIndex,
        #[SoapName('count')]
        public int $count,
    ) {
    }
}
