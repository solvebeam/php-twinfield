<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SpreadTransaction;

use SolveBeam\Twinfield\Transaction\TransactionReference;

/**
 * The spread of a sales or purchase transaction: the original transaction, the spread settings
 * and the transactions Twinfield made for it. One of those moves the amounts of the detail lines
 * to the intermediate account in the period of the original; the others spread them from there
 * over the periods.
 */
final readonly class Spread
{
    /**
     * @param list<TransactionReference> $spreadTransactions
     */
    public function __construct(
        public TransactionReference $original,
        /** Null when the transaction has no spread settings. */
        public ?SpreadSettings $settings,
        /**
         * The transactions Twinfield made for the spread; read them with `journalTransactions()`.
         * Empty when there are none.
         */
        public array $spreadTransactions,
    ) {
    }

    /**
     * True when Twinfield made spread transactions for the original transaction.
     */
    public function isSpread(): bool
    {
        return $this->spreadTransactions !== [];
    }
}
