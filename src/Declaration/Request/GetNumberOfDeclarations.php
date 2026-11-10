<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetNumberOfDeclarations (declarations.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('GetNumberOfDeclarations')]
#[SoapAction(action: 'http://www.twinfield.com/GetNumberOfDeclarations')]
final readonly class GetNumberOfDeclarations
{
    public function __construct(
        #[SoapName('companyCode')]
        public string $companyCode,
    ) {
    }
}
