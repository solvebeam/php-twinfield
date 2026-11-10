<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetRangeOfSummariesByDeclarationYear (declarations.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('GetRangeOfSummariesByDeclarationYear')]
#[SoapAction(action: 'http://www.twinfield.com/GetRangeOfSummariesByDeclarationYear')]
final readonly class GetRangeOfSummariesByDeclarationYear
{
    public function __construct(
        #[SoapName('companyCode')]
        public string $companyCode,
        #[SoapName('declarationYear')]
        public int $declarationYear,
        #[SoapName('startIndex')]
        public int $startIndex,
        #[SoapName('count')]
        public int $count,
    ) {
    }
}
