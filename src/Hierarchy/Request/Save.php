<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of Save (hierarchies.asmx).
 *
 * @internal
 */
#[SoapName('Save')]
#[SoapAction(action: 'http://www.twinfield.com/Save')]
final readonly class Save
{
    public function __construct(
        #[SoapName('hierarchy')]
        public HierarchyBody $hierarchy,
    ) {
    }
}
