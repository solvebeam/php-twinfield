<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Result of saveMany(): per input, in the same order, a SavedTransaction or a FailedTransaction.
 *
 *   $result = $twinfield->bankTransactions()->saveMany(...$inputs);
 *
 *   foreach ($result->saved() as $saved) {
 *       echo 'Saved ', $saved->transaction->number(), PHP_EOL;
 *   }
 *
 *   foreach ($result->failed() as $failed) {
 *       echo 'Failed #', $failed->index, ': ', $failed->message(), PHP_EOL;
 *   }
 *
 * Twinfield saves the valid transactions even when others fail.
 *
 * @template T of object
 *
 * @implements IteratorAggregate<int, SavedTransaction<T>|FailedTransaction>
 */
final readonly class SaveManyResult implements IteratorAggregate, Countable
{
    /**
     * @param list<SavedTransaction<T>|FailedTransaction> $results
     */
    public function __construct(
        public array $results,
    ) {
    }

    /**
     * @return list<SavedTransaction<T>>
     */
    public function saved(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (SavedTransaction|FailedTransaction $result): bool => $result instanceof SavedTransaction,
        ));
    }

    /**
     * @return list<FailedTransaction>
     */
    public function failed(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (SavedTransaction|FailedTransaction $result): bool => $result instanceof FailedTransaction,
        ));
    }

    public function allSaved(): bool
    {
        return $this->failed() === [];
    }

    public function count(): int
    {
        return count($this->results);
    }

    /**
     * @return Traversable<int, SavedTransaction<T>|FailedTransaction>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }
}
