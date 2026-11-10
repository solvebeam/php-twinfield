<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetTaxGroupVatReturnPaymentReference (declarations.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('GetTaxGroupVatReturnPaymentReference')]
#[SoapAction(action: 'http://www.twinfield.com/GetTaxGroupVatReturnPaymentReference')]
final readonly class GetTaxGroupVatReturnPaymentReference
{
    public function __construct(
        #[SoapName('documentId')]
        public int $documentId,
    ) {
    }
}
