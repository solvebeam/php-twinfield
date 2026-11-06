<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

/**
 * A Twinfield office (administration / company).
 */
final readonly class Office
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }
}
