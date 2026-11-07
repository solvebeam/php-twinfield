<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use LogicException;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Twinfield;

/**
 * Budgets of the company, via the BudgetService (a Command & Query web service, read-only).
 *
 *   $budgets = $twinfield->company('1000')->budgets();
 *
 *   foreach ($budgets->search()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $totals = $budgets->totals('001', 2025)->periods(1, 6)->byProfitAndLoss();
 *
 *   foreach ($totals as $total) {
 *       echo $total->period, ' ', $total->generalLedgerAccount, ' ',
 *           $total->budget->total, ' ', $total->actual->total, PHP_EOL;
 *   }
 *
 * Budgets cannot be created or changed through the API.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/budget/
 */
final readonly class BudgetResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Search budgets by code and name, e.g. `search('2025*')->limit(10)->get()`.
     *
     * Finder type `BDS`; the company comes from the client, so no `company` option is sent.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<BudgetSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        $query = $this->twinfield->finder(FinderType::Budgets);

        return (new CodeNameSearch($query, BudgetSummary::fromFinderRow(...)))->pattern($pattern);
    }

    /**
     * Query the budgeted and actual amounts of a budget in a year, e.g.
     * `totals('001', 2025)->periods(1, 6)->byProfitAndLoss()`.
     */
    public function totals(string $code, int $year): BudgetTotalsQuery
    {
        if ($this->twinfield->companyCode() === null) {
            throw new LogicException('Budgets belong to a company: use company() first.');
        }

        return BudgetTotalsQuery::create($this->twinfield, $code, $year);
    }
}
