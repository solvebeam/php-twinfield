<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Lines of a budget query, in the order of Twinfield. Immutable: filters return new totals.
 *
 *   foreach ($totals->forPeriod(3) as $total) { … }
 *
 *   foreach ($totals->generalLedgerAccounts() as $account) {
 *       $lines = $totals->forGeneralLedgerAccount($account);
 *   }
 *
 * Twinfield only returns lines with a budget or actual amount.
 *
 * @implements IteratorAggregate<int, BudgetTotal>
 */
final readonly class BudgetTotals implements IteratorAggregate, Countable
{
    /**
     * @param list<BudgetTotal> $totals
     */
    public function __construct(
        private array $totals,
    ) {
    }

    public function forPeriod(int $period): self
    {
        return $this->filter(static fn (BudgetTotal $total): bool => $total->period === $period);
    }

    public function forGeneralLedgerAccount(string $code): self
    {
        return $this->filter(static fn (BudgetTotal $total): bool => $total->generalLedgerAccount === $code);
    }

    /**
     * Lines of this dimension 2, e.g. a cost center.
     */
    public function forDimension2(string $code): self
    {
        return $this->filter(static fn (BudgetTotal $total): bool => $total->dimension2 === $code);
    }

    /**
     * Lines of this dimension 3, e.g. a project.
     */
    public function forDimension3(string $code): self
    {
        return $this->filter(static fn (BudgetTotal $total): bool => $total->dimension3 === $code);
    }

    /**
     * Period numbers with lines, sorted, e.g. `[1, 2, 3]`.
     *
     * @return list<int>
     */
    public function periods(): array
    {
        $periods = array_values(array_unique(array_map(
            static fn (BudgetTotal $total): int => $total->period,
            $this->totals,
        )));
        sort($periods);

        return $periods;
    }

    /**
     * General ledger account codes with lines, sorted.
     *
     * @return list<string>
     */
    public function generalLedgerAccounts(): array
    {
        $accounts = array_values(array_unique(array_map(
            static fn (BudgetTotal $total): string => $total->generalLedgerAccount,
            $this->totals,
        )));
        sort($accounts, SORT_STRING);

        return $accounts;
    }

    public function isEmpty(): bool
    {
        return $this->totals === [];
    }

    public function count(): int
    {
        return count($this->totals);
    }

    /**
     * @return Traversable<int, BudgetTotal>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->totals);
    }

    /**
     * @return list<BudgetTotal>
     */
    public function toArray(): array
    {
        return $this->totals;
    }

    /**
     * @param callable(BudgetTotal): bool $keep
     */
    private function filter(callable $keep): self
    {
        return new self(array_values(array_filter($this->totals, $keep)));
    }
}
