<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

/**
 * Status of a dimension (`dimension/@status`).
 */
enum DimensionStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';

    /** Twinfield hides a dimension instead of deleting it when it is in use. */
    case Hidden = 'hide';
}
