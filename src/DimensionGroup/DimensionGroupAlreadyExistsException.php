<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionGroup;

use RuntimeException;

/**
 * create() was called for a code that already exists. Use update() or save() to change it.
 */
final class DimensionGroupAlreadyExistsException extends RuntimeException
{
    public function __construct(
        public readonly string $dimensionGroupCode,
    ) {
        parent::__construct(sprintf('Dimension group %s already exists.', $dimensionGroupCode));
    }
}
