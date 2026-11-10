<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of SetToRejected (declarations.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('SetToRejected')]
#[SoapAction(action: 'http://www.twinfield.com/SetToRejected')]
final readonly class SetToRejected
{
    public function __construct(
        #[SoapName('documentId')]
        public int $documentId,
        #[SoapName('reason')]
        public string $reason,
    ) {
    }
}
