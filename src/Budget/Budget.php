<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A budget (budget scenario) of the REST API, with its GUID. `search()` finds the same budgets by code
 * with the Finder.
 */
final readonly class Budget
{
    public function __construct(
        public CompanyReference $company,
        /** GUID of the budget. */
        public string $id,
        /** Code, e.g. `001`. */
        public string $code,
        public string $name,
        public ?string $description,
        /** True when the budget also has lines for the system periods (0 and 55 and up). */
        public bool $useSystemPeriods,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastModifiedAt,
    ) {
        Guid::of($id);

        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('Budget code and name must not be empty.');
        }
    }

    /**
     * Input to change the name, description or system periods; the code can't change.
     */
    public function edit(): BudgetInput
    {
        return BudgetInput::create($this->code, $this->name)
            ->description($this->description)
            ->useSystemPeriods($this->useSystemPeriods);
    }
}
