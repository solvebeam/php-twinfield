<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetRunInfo (payandcollect.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('GetRunInfo')]
#[SoapAction(action: 'http://www.twinfield.com/GetRunInfo')]
final readonly class GetRunInfo
{
    public function __construct(
        #[SoapName('office')]
        public string $office,
        #[SoapName('payType')]
        public string $payType,
        #[SoapName('runId')]
        public int $runId,
    ) {
    }
}
