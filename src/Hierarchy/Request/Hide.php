<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of Hide (hierarchies.asmx).
 *
 * @internal
 */
#[SoapName('Hide')]
#[SoapAction(action: 'http://www.twinfield.com/Hide')]
final readonly class Hide
{
    public function __construct(
        #[SoapName('hierarchyCode')]
        public string $hierarchyCode,
    ) {
    }
}
