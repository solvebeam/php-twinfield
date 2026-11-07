<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CashBook;

use RuntimeException;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;

/**
 * There is no cash book with this code in the company.
 */
final class CashBookNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $cashBookCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?CommandQueryFaultException $previous = null,
    ) {
        parent::__construct(sprintf('Cash book %s not found: %s', $cashBookCode, $reason), 0, $previous);
    }
}
