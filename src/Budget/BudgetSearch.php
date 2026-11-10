<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use DateTimeInterface;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;

/**
 * Search on the budgets of the REST API, created by `budgets()->list()`.
 *
 * @extends RestSearch<Budget>
 */
final readonly class BudgetSearch extends RestSearch
{
    public function pattern(string $pattern): self
    {
        return $this->with('searchPattern', $pattern);
    }

    public function modifiedSince(DateTimeInterface $since): self
    {
        return $this->with('modifiedSince', $since->format(DATE_ATOM));
    }

    /**
     * Sort by a field, e.g. `code` or `name`.
     */
    public function orderBy(string $field, bool $ascending = true): self
    {
        return $this->sort($field, $ascending);
    }

    protected function map(JsonValue $item, CompanyReference $company): Budget
    {
        return BudgetReader::budget($item, $company);
    }
}
