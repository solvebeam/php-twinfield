<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Soap\SoapArray;

/**
 * `ArrayOfHierarchyNode`.
 *
 * @internal
 */
#[SoapArray(elementName: 'HierarchyNode')]
final readonly class NodeList
{
    /**
     * @param list<NodeBody> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
