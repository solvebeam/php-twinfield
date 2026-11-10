<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use SolveBeam\Twinfield\Period\Period;

/**
 * A reference that changes over time: the cost center or location of a fixed asset from a period on.
 */
final readonly class TimeRelatedReference
{
    public function __construct(
        /** GUID of the cost center or location. */
        public string $id,
        public Period $from,
    ) {
    }
}
