<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of CreateRunAsync (payandcollect.asmx). The parameter is `createRunAsyncRequest` in the
 * WSDL; the documentation calls it `createRunRequest`.
 *
 * @internal
 */
#[SoapName('CreateRunAsync')]
#[SoapAction(action: 'http://www.twinfield.com/CreateRunAsync')]
final readonly class CreateRunAsync
{
    public function __construct(
        #[SoapName('createRunAsyncRequest')]
        public CreateRunRequest $request,
    ) {
    }
}
