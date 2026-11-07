<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use InvalidArgumentException;

/**
 * Debit, credit and total of budgeted or actual amounts.
 *
 * Amounts are exact decimal strings as Twinfield returns them, e.g. `474.3600`, `-70.0100`
 * or `0`; never floats. Use e.g. `bcadd()` or a money library to calculate with them.
 */
final readonly class BudgetAmounts
{
    /**
     * @param numeric-string $debit
     * @param numeric-string $credit
     * @param numeric-string $total
     */
    public function __construct(
        /** Debit amount. */
        public string $debit,
        /** Credit amount, positive. Corrections can make it negative. */
        public string $credit,
        /** Debit minus credit: positive for debit, negative for credit. */
        public string $total,
    ) {
        foreach (['debit' => $debit, 'credit' => $credit, 'total' => $total] as $name => $amount) {
            if (preg_match('~^-?\d+(\.\d+)?$~', $amount) !== 1) {
                throw new InvalidArgumentException(sprintf('Budget %s amount "%s" is not a decimal.', $name, $amount));
            }
        }
    }
}
