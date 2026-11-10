<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

/**
 * Status of a changed customer or supplier in the REST API.
 */
enum DimensionChangeStatus: string
{
    case Active = 'active';
    /** Hidden in Twinfield (`hide` in the XML web services). */
    case Archived = 'archived';
    case Deleted = 'deleted';
}
