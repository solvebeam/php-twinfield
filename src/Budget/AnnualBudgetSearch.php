<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use DateTimeInterface;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;

/**
 * The budgets with their balance per year, created by `budgets()->annual()`.
 *
 * @extends RestSearch<AnnualBudget>
 */
final readonly class AnnualBudgetSearch extends RestSearch
{
    public function pattern(string $pattern): self
    {
        return $this->with('searchPattern', $pattern);
    }

    public function modifiedSince(DateTimeInterface $since): self
    {
        return $this->with('modifiedSince', $since->format(DATE_ATOM));
    }

    public function orderBy(string $field, bool $ascending = true): self
    {
        return $this->sort($field, $ascending);
    }

    protected function map(JsonValue $item, CompanyReference $company): AnnualBudget
    {
        return new AnnualBudget(BudgetReader::budget($item, $company), $item->int('year'), $item->amount('balance'));
    }
}
