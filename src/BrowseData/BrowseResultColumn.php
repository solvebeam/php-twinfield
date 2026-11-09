<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

/**
 * A column of a browse result (`<th><td>`): the field and the type of its values.
 */
final readonly class BrowseResultColumn
{
    public function __construct(
        /** E.g. `fin.trs.line.valuesigned`. */
        public string $field,
        public BrowseValueType $type,
    ) {
    }
}
