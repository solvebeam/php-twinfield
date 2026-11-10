<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A customer or supplier that changed, from `customers()->changes()` or `suppliers()->changes()`
 * (REST API). It has no code: the GUID is the `uid` of the dimension, e.g. `$customer->uid`.
 */
final readonly class DimensionChange
{
    public function __construct(
        public CompanyReference $company,
        /** GUID of the customer or supplier, the `uid` in the XML web services. */
        public string $id,
        public string $name,
        public DimensionChangeStatus $status,
        /** When it last changed. */
        public DateTimeImmutable $modified,
    ) {
        Guid::of($id);

        if ($name === '') {
            throw new InvalidArgumentException('Changed dimension name must not be empty.');
        }
    }
}
