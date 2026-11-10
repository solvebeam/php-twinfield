<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use DateTimeInterface;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;

/**
 * Search on the lines of a budget in a year, created by `budgets()->lines($budgetId, $year)`.
 *
 * @extends RestSearch<BudgetLine>
 */
final readonly class BudgetLineSearch extends RestSearch
{
    public function period(int $period): self
    {
        return $this->with('period', $period);
    }

    public function type(BudgetLineType $type): self
    {
        return $this->with('type', $type->value);
    }

    public function generalLedgerAccount(string $id): self
    {
        return $this->with('generalLedgerId', Guid::of($id, 'General ledger account ID'));
    }

    public function costCenter(string $id): self
    {
        return $this->with('costCenterId', Guid::of($id, 'Cost center ID'));
    }

    public function project(string $id): self
    {
        return $this->with('projectId', Guid::of($id, 'Project ID'));
    }

    public function modifiedSince(DateTimeInterface $since): self
    {
        return $this->with('modifiedSince', $since->format(DATE_ATOM));
    }

    public function orderBy(string $field, bool $ascending = true): self
    {
        return $this->sort($field, $ascending);
    }

    protected function map(JsonValue $item, CompanyReference $company): BudgetLine
    {
        return BudgetReader::line($item);
    }
}
