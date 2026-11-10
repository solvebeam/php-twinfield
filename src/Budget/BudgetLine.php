<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use DateTimeImmutable;

/**
 * A line of a budget: the budgeted debit and credit of an account in a period.
 */
final readonly class BudgetLine
{
    public function __construct(
        /** GUID of the budget. */
        public string $budgetId,
        public BudgetLineKey $key,
        /**
         * Debit: an amount for UnitOfMeasure::Monetary, else a quantity.
         *
         * @var numeric-string
         */
        public string $debit,
        /** @var numeric-string */
        public string $credit,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastModifiedAt,
    ) {
    }
}
