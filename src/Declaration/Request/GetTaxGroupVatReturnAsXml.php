<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetTaxGroupVatReturnAsXml (declarations.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('GetTaxGroupVatReturnAsXml')]
#[SoapAction(action: 'http://www.twinfield.com/GetTaxGroupVatReturnAsXml')]
final readonly class GetTaxGroupVatReturnAsXml
{
    public function __construct(
        #[SoapName('documentId')]
        public int $documentId,
    ) {
    }
}
