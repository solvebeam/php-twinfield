<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of SetToSent (declarations.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('SetToSent')]
#[SoapAction(action: 'http://www.twinfield.com/SetToSent')]
final readonly class SetToSent
{
    public function __construct(
        #[SoapName('documentId')]
        public int $documentId,
    ) {
    }
}
