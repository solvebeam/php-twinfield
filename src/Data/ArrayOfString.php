<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Data;

use ArrayIterator;
use IteratorAggregate;
use SolveBeam\Twinfield\Soap\SoapArray;

/**
 * @implements IteratorAggregate<int, string>
 */
#[SoapArray(elementName: 'string')]
final readonly class ArrayOfString implements IteratorAggregate
{
    /** @param string[] $items */
    public function __construct(
        public array $items,
    ) {
    }

    /** @return ArrayIterator<int, string> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }
}
