<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rate;

use RuntimeException;

/**
 * create() was called for a code that already exists. Use update() or save() to change it.
 */
final class RateAlreadyExistsException extends RuntimeException
{
    public function __construct(
        public readonly string $rateCode,
    ) {
        parent::__construct(sprintf('Rate %s already exists.', $rateCode));
    }
}
