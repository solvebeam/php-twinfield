<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Data;

use ArrayIterator;
use IteratorAggregate;
use SolveBeam\Twinfield\Soap\SoapArray;

/**
 * @implements IteratorAggregate<int, ArrayOfString>
 *
 * @internal
 */
#[SoapArray(elementName: 'ArrayOfString', type: ArrayOfString::class)]
final readonly class ArrayOfArrayOfString implements IteratorAggregate
{
    /** @param ArrayOfString[] $items */
    public function __construct(
        public array $items,
    ) {
    }

    /** @return ArrayIterator<int, ArrayOfString> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }
}
