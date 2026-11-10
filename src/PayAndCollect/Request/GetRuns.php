<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetRuns (payandcollect.asmx).
 *
 * @internal
 */
#[SoapName('GetRuns')]
#[SoapAction(action: 'http://www.twinfield.com/GetRuns')]
final readonly class GetRuns
{
    public function __construct(
        #[SoapName('office')]
        public string $office,
    ) {
    }
}
