<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of CreateRun2 (payandcollect.asmx).
 *
 * @internal
 */
#[SoapName('CreateRun2')]
#[SoapAction(action: 'http://www.twinfield.com/CreateRun2')]
final readonly class CreateRun2
{
    public function __construct(
        #[SoapName('createRunRequest')]
        public CreateRunRequest $request,
    ) {
    }
}
