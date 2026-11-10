<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * One page of a list of the REST API, e.g. `$twinfield->ledgerAccounts()->search()->limit(100)->get()`.
 *
 * @template T of object
 *
 * @implements IteratorAggregate<int, T>
 */
final readonly class RestPage implements IteratorAggregate, Countable
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        /** Items of the company without the filters, e.g. all ledger accounts. */
        public int $total,
        /** Items that match the filters, over all pages. */
        public int $filteredTotal,
        /** Position of the first item of this page in all matches, from 0. */
        public int $offset,
    ) {
    }

    /**
     * True when there are matches after this page.
     */
    public function hasMore(): bool
    {
        return $this->offset + count($this->items) < $this->filteredTotal;
    }

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
