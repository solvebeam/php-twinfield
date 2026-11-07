<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use SolveBeam\Twinfield\ProcessXml\ProcessXmlMessage;

/**
 * A transaction of saveMany() that Twinfield saved.
 *
 * @template T of object
 */
final readonly class SavedTransaction
{
    /**
     * @param T $transaction
     * @param list<ProcessXmlMessage> $warnings
     */
    public function __construct(
        /** Position in the inputs of saveMany(), from 0. */
        public int $index,
        public TransactionInput $input,
        public object $transaction,
        public array $warnings,
    ) {
    }
}
