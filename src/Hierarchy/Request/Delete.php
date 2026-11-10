<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of Delete (hierarchies.asmx).
 *
 * @internal
 */
#[SoapName('Delete')]
#[SoapAction(action: 'http://www.twinfield.com/Delete')]
final readonly class Delete
{
    public function __construct(
        #[SoapName('hierarchyCode')]
        public string $hierarchyCode,
    ) {
    }
}
