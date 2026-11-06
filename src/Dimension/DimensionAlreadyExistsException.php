<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use RuntimeException;

/**
 * create() was called for a code that already exists. Use update() or save() to change it.
 */
final class DimensionAlreadyExistsException extends RuntimeException
{
    public function __construct(
        /** Dimension type, e.g. `ACT`. */
        public readonly string $dimensionType,
        public readonly string $dimensionCode,
    ) {
        parent::__construct(sprintf('Dimension %s %s already exists.', $dimensionType, $dimensionCode));
    }
}
