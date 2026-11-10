<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of Load (hierarchies.asmx).
 *
 * @internal
 */
#[SoapName('Load')]
#[SoapAction(action: 'http://www.twinfield.com/Load')]
final readonly class Load
{
    public function __construct(
        #[SoapName('hierarchyCode')]
        public string $hierarchyCode,
    ) {
    }
}
