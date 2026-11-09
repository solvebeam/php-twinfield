<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

/**
 * The `type` attribute of a column in a browse result (`<th><td type="Value">`).
 *
 * Not documented as a list; these are the types seen in live responses.
 */
enum BrowseValueType: string
{
    /** Text; can be empty. */
    case String = 'String';

    /** A whole number, e.g. a year or period number. */
    case Number = 'Number';

    /** A number, e.g. a transaction number (`202600003`, `-1` for a draft) or a quantity; can be empty. */
    case Decimal = 'Decimal';

    /** An amount with 2 decimals, e.g. `-121.00`. */
    case Value = 'Value';

    /** A date `yyyymmdd`. */
    case Date = 'Date';

    /** A date and time `yyyymmddhhmmss`, in UTC. */
    case DateTime = 'Datetime';
}
