<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use ArrayIterator;
use LogicException;
use RecursiveIterator;

/**
 * Recursive iterator over nodes of a reporting structure, without accounts. The children of a
 * node are its child nodes. Wrap it in RecursiveIteratorIterator to walk the whole tree, or in
 * RecursiveTreeIterator to draw it:
 *
 *   foreach (new RecursiveTreeIterator($hierarchy->nodes()) as $line) {
 *       echo $line, PHP_EOL;
 *   }
 *
 *   \-TWFRGS32 RGS  3.2
 *     |-B BALANS
 *     | |-BIVA Immateriële vaste activa
 *     | | |-BIVAKOU Kosten van oprichting en van uitgifte van aandelen
 *     …
 *
 * @extends ArrayIterator<int, HierarchyNode>
 * @implements RecursiveIterator<int, HierarchyNode>
 */
final class HierarchyNodeIterator extends ArrayIterator implements RecursiveIterator
{
    /**
     * @param list<HierarchyNode> $nodes
     */
    public function __construct(array $nodes)
    {
        parent::__construct($nodes);
    }

    public function hasChildren(): bool
    {
        return $this->valid() && $this->current()->children !== [];
    }

    public function getChildren(): self
    {
        if (!$this->valid()) {
            throw new LogicException('The iterator is not on a node.');
        }

        return new self($this->current()->children);
    }
}
