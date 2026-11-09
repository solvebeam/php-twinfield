<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

/**
 * A value of a browse field with a fixed list of values, e.g. `final` of `fin.trs.head.status`.
 */
final readonly class BrowseFieldOption
{
    public function __construct(
        /** The value to use in a criterion, e.g. `final`. */
        public string $value,
        /** The name in the language of the user, e.g. `Definitief`; null when Twinfield gives none. */
        public ?string $name,
    ) {
    }
}
