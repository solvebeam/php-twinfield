<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CashBook;

use RuntimeException;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;

/**
 * create() was called for a code that is already in use. Use update() to change the cash book.
 */
final class CashBookAlreadyExistsException extends RuntimeException
{
    public function __construct(
        public readonly string $cashBookCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?CommandQueryFaultException $previous = null,
    ) {
        parent::__construct(sprintf('Cash book %s already exists: %s', $cashBookCode, $reason), 0, $previous);
    }
}
