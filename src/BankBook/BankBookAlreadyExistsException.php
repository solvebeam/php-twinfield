<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankBook;

use RuntimeException;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;

/**
 * create() was called for a code that is already in use. Use update() to change the bank book.
 */
final class BankBookAlreadyExistsException extends RuntimeException
{
    public function __construct(
        public readonly string $bankBookCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?CommandQueryFaultException $previous = null,
    ) {
        parent::__construct(sprintf('Bank book %s already exists: %s', $bankBookCode, $reason), 0, $previous);
    }
}
