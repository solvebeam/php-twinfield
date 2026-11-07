<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionGroup;

use RuntimeException;

/**
 * The dimension group does not exist in the company (or is deleted).
 */
final class DimensionGroupNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $dimensionGroupCode,
    ) {
        parent::__construct(sprintf('Dimension group %s not found.', $dimensionGroupCode));
    }
}
