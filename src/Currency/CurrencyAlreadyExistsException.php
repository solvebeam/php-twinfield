<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Currency;

use RuntimeException;

/**
 * create() was called for a code that already exists. Use update() or save() to change it.
 */
final class CurrencyAlreadyExistsException extends RuntimeException
{
    public function __construct(
        public readonly string $currencyCode,
    ) {
        parent::__construct(sprintf('Currency %s already exists.', $currencyCode));
    }
}
