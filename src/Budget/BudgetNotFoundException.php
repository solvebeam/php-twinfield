<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use RuntimeException;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;

/**
 * There is no budget with this code in the company.
 */
final class BudgetNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $budgetCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?CommandQueryFaultException $previous = null,
    ) {
        parent::__construct(sprintf('Budget %s not found: %s', $budgetCode, $reason), 0, $previous);
    }
}
