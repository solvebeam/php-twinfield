<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

/**
 * How Twinfield treats a dimension (`dimension/behaviour`, read-only).
 */
enum DimensionBehaviour: string
{
    case Normal = 'normal';
    case System = 'system';
    case Template = 'template';
}
