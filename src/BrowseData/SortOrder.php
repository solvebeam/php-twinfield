<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

/**
 * The `order` attribute of a browse sort field.
 */
enum SortOrder: string
{
    case Ascending = 'ascending';
    case Descending = 'descending';
}
