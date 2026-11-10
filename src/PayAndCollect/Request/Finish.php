<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of Finish (payandcollect.asmx). Fields in WSDL order.
 * The pay type is `payRunCode` here, `payType` in the other operations.
 *
 * @internal
 */
#[SoapName('Finish')]
#[SoapAction(action: 'http://www.twinfield.com/Finish')]
final readonly class Finish
{
    public function __construct(
        #[SoapName('office')]
        public string $office,
        #[SoapName('payRunCode')]
        public string $payType,
        #[SoapName('runId')]
        public int $runId,
        #[SoapName('touched')]
        public int $touched,
        /** `modified` exactly as Twinfield sent it. */
        #[SoapName('modified')]
        public string $modified,
    ) {
    }
}
