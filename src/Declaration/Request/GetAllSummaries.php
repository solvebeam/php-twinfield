<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetAllSummaries (declarations.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('GetAllSummaries')]
#[SoapAction(action: 'http://www.twinfield.com/GetAllSummaries')]
final readonly class GetAllSummaries
{
    public function __construct(
        #[SoapName('companyCode')]
        public string $companyCode,
    ) {
    }
}
