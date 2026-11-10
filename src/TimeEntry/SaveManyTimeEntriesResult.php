<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Result of saveMany(): per input, in the same order, a SavedTimeEntry or a FailedTimeEntry.
 *
 *   $result = $twinfield->timeEntries()->saveMany($monday, $tuesday);
 *
 *   foreach ($result->failed() as $failed) {
 *       echo 'Failed #', $failed->index, ': ', $failed->message(), PHP_EOL;
 *   }
 *
 * @implements IteratorAggregate<int, SavedTimeEntry|FailedTimeEntry>
 */
final readonly class SaveManyTimeEntriesResult implements IteratorAggregate, Countable
{
    /**
     * @param list<SavedTimeEntry|FailedTimeEntry> $results
     */
    public function __construct(
        public array $results,
    ) {
    }

    /**
     * @return list<SavedTimeEntry>
     */
    public function saved(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (SavedTimeEntry|FailedTimeEntry $result): bool => $result instanceof SavedTimeEntry,
        ));
    }

    /**
     * @return list<FailedTimeEntry>
     */
    public function failed(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (SavedTimeEntry|FailedTimeEntry $result): bool => $result instanceof FailedTimeEntry,
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
     * @return Traversable<int, SavedTimeEntry|FailedTimeEntry>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }
}
