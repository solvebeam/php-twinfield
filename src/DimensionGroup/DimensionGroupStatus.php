<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionGroup;

/**
 * Status of a dimension group (`dimensiongroup/@status`).
 */
enum DimensionGroupStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';
}
