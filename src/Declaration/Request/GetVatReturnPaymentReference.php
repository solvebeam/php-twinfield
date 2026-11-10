<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetVatReturnPaymentReference (declarations.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('GetVatReturnPaymentReference')]
#[SoapAction(action: 'http://www.twinfield.com/GetVatReturnPaymentReference')]
final readonly class GetVatReturnPaymentReference
{
    public function __construct(
        #[SoapName('documentId')]
        public int $documentId,
    ) {
    }
}
