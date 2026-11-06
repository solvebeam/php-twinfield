<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * One page of a dimension search.
 *
 * @template T of object
 *
 * @implements IteratorAggregate<int, T>
 */
final readonly class DimensionSearchResult implements IteratorAggregate, Countable
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        /** Matches over all pages. */
        public int $totalRows,
        public array $items,
    ) {
    }

    /**
     * Results on this page; see $totalRows for the total over all pages.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return ArrayIterator<int, T>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }
}
