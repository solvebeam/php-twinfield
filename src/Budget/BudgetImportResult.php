<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

/**
 * Result of `budgets()->import()`.
 */
final readonly class BudgetImportResult
{
    /**
     * @param array<array-key, mixed> $data Details as Twinfield returns them.
     */
    public function __construct(
        public bool $success,
        /** Why the import failed; null when it succeeded. */
        public ?string $error,
        public ?string $location,
        public array $data,
    ) {
    }
}
