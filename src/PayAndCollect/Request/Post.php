<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of Post (payandcollect.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('Post')]
#[SoapAction(action: 'http://www.twinfield.com/Post')]
final readonly class Post
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
