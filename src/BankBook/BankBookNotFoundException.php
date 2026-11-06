<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankBook;

use RuntimeException;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;

/**
 * There is no bank book with this code in the company. Cash books are not bank books:
 * the BankBookService answers "not found" for them too.
 */
final class BankBookNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $bankBookCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?CommandQueryFaultException $previous = null,
    ) {
        parent::__construct(sprintf('Bank book %s not found: %s', $bankBookCode, $reason), 0, $previous);
    }
}
