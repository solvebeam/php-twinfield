<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Soap\SoapArray;

/**
 * `ArrayOfHierarchyAccess`.
 *
 * @internal
 */
#[SoapArray(elementName: 'HierarchyAccess')]
final readonly class AccessList
{
    /**
     * @param list<AccessBody> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
