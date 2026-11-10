<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetVatReturnAsXml (declarations.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('GetVatReturnAsXml')]
#[SoapAction(action: 'http://www.twinfield.com/GetVatReturnAsXml')]
final readonly class GetVatReturnAsXml
{
    public function __construct(
        #[SoapName('documentId')]
        public int $documentId,
    ) {
    }
}
