<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use InvalidArgumentException;

/**
 * A Twinfield office (administration / company).
 *
 * Twinfield is moving from the term "office" to "company"; this class keeps the old name for now.
 */
final readonly class Office
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('Office code must not be empty.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('Office name must not be empty.');
        }
    }
}
