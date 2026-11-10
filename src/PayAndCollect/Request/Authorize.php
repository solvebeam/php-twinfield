<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of Authorize (payandcollect.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('Authorize')]
#[SoapAction(action: 'http://www.twinfield.com/Authorize')]
final readonly class Authorize
{
    public function __construct(
        #[SoapName('office')]
        public string $office,
        #[SoapName('payType')]
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
