<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * A reference that changes per year: the class of a fixed asset from a year on.
 */
final readonly class YearRelatedReference
{
    public function __construct(
        /** GUID of the class. */
        public string $id,
        public int $year,
    ) {
    }
}
