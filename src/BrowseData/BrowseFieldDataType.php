<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

/**
 * The `datatype` of a browse field (`<list><type>browsefields</type></list>`).
 */
enum BrowseFieldDataType: string
{
    case String = 'string';

    /** A fixed list of values: see `BrowseField::$options`. */
    case Special = 'special';

    /** An amount. */
    case Value = 'value';

    case Long = 'long';
    case Decimal = 'decimal';
    case Date = 'date';
    case DateTime = 'datetime';
}
