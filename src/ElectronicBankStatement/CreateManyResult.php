<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Result of createMany(): per input, in the same order, a CreatedStatement or a FailedStatement.
 *
 *   $result = $twinfield->electronicBankStatements()->createMany(...$inputs);
 *
 *   foreach ($result->failed() as $failed) {
 *       echo 'Failed #', $failed->index, ': ', $failed->message(), PHP_EOL;
 *   }
 *
 * @implements IteratorAggregate<int, CreatedStatement|FailedStatement>
 */
final readonly class CreateManyResult implements IteratorAggregate, Countable
{
    /**
     * @param list<CreatedStatement|FailedStatement> $results
     */
    public function __construct(
        public array $results,
    ) {
    }

    /**
     * @return list<CreatedStatement>
     */
    public function created(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (CreatedStatement|FailedStatement $result): bool => $result instanceof CreatedStatement,
        ));
    }

    /**
     * @return list<FailedStatement>
     */
    public function failed(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (CreatedStatement|FailedStatement $result): bool => $result instanceof FailedStatement,
        ));
    }

    public function allCreated(): bool
    {
        return $this->failed() === [];
    }

    public function count(): int
    {
        return count($this->results);
    }

    /**
     * @return Traversable<int, CreatedStatement|FailedStatement>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }
}
