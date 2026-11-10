<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use ArrayIterator;
use LogicException;
use RecursiveIterator;

/**
 * Recursive iterator over nodes and accounts of a reporting structure. The children of a node are
 * its accounts, then its child nodes; an account has no children. This is what you get when you
 * iterate a Hierarchy or a HierarchyNode:
 *
 *   foreach (new RecursiveTreeIterator($hierarchy) as $line) {
 *       echo $line, PHP_EOL;
 *   }
 *
 *   \-MGMT Management report
 *     |-1 Activa
 *     | |-11 Immateriële vaste activa
 *     | | |-0100 (BAS, Balance)
 *     | | \-0110 (BAS, Balance)
 *     …
 *
 * RecursiveIteratorIterator gives the leaves: the accounts, and the nodes without accounts and
 * child nodes. For the accounts only, use `$hierarchy->rootNode->allAccounts()`.
 *
 * @extends ArrayIterator<int, HierarchyNode|HierarchyAccount>
 * @implements RecursiveIterator<int, HierarchyNode|HierarchyAccount>
 */
final class HierarchyNodeAccountIterator extends ArrayIterator implements RecursiveIterator
{
    /**
     * @param list<HierarchyNode|HierarchyAccount> $items
     */
    public function __construct(array $items)
    {
        parent::__construct($items);
    }

    public function hasChildren(): bool
    {
        $current = $this->current();

        return $current instanceof HierarchyNode && ($current->accounts !== [] || $current->children !== []);
    }

    public function getChildren(): self
    {
        $current = $this->current();

        if (!$current instanceof HierarchyNode) {
            throw new LogicException('The iterator is not on a node.');
        }

        return new self([...$current->accounts, ...$current->children]);
    }
}
