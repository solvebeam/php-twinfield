<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

/**
 * Operator of a browse column: how its `from` and `to` values select rows.
 *
 * The documentation lists `none`, `equal` and `between`. `like` (in the definitions of 130 and
 * 230) and `notbetween` are not documented but work. `lessthan` (old manual) and `in`/`notin`
 * (named in a Twinfield error message) give a server error, so they are not here.
 */
enum BrowseOperator: string
{
    /** No selection; `from` and `to` are ignored. */
    case None = 'none';

    /** Rows with the `from` value; `to` is ignored. */
    case Equal = 'equal';

    /**
     * Rows matching the pattern in `from`: `*` for any characters, `?` for one; `to` is ignored.
     */
    case Like = 'like';

    /** Rows from `from` up to and including `to`; both are required. */
    case Between = 'between';

    /** Rows outside `from` … `to`; both are required. */
    case NotBetween = 'notbetween';
}
