<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of SetToApproved (declarations.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('SetToApproved')]
#[SoapAction(action: 'http://www.twinfield.com/SetToApproved')]
final readonly class SetToApproved
{
    public function __construct(
        #[SoapName('documentId')]
        public int $documentId,
    ) {
    }
}
