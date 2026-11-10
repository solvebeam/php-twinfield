<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetIctReturnAsXml (declarations.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('GetIctReturnAsXml')]
#[SoapAction(action: 'http://www.twinfield.com/GetIctReturnAsXml')]
final readonly class GetIctReturnAsXml
{
    public function __construct(
        #[SoapName('documentId')]
        public int $documentId,
    ) {
    }
}
