<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

/**
 * What Twinfield did with a delete request.
 */
enum DeleteResult
{
    /** The dimension is deleted; activate() brings it back. */
    case Deleted;

    /** The dimension is in use, so Twinfield hid it instead; activate() shows it again. */
    case Hidden;
}
