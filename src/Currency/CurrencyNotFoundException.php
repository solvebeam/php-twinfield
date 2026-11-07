<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Currency;

use RuntimeException;

/**
 * The currency does not exist in the company.
 */
final class CurrencyNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $currencyCode,
    ) {
        parent::__construct(sprintf('Currency %s not found.', $currencyCode));
    }
}
