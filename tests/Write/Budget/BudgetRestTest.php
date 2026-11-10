<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\Budget;

use SolveBeam\Twinfield\Budget\BudgetInput;
use SolveBeam\Twinfield\Budget\BudgetLineKey;
use SolveBeam\Twinfield\Budget\PeriodWeight;
use SolveBeam\Twinfield\Budget\SpreadFor;
use SolveBeam\Twinfield\Budget\UnitOfMeasure;
use SolveBeam\Twinfield\LedgerAccount\LedgerAccountType;
use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Rest\NotFoundException;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates a temporary budget with lines and a spreading template (REST API), and deletes them again.
 *
 * Not run live yet: needs `composer test:write` and the scopes writeBudgets and writeBudgetScenarios.
 */
final class BudgetRestTest extends WriteTestCase
{
    public function testBudgetLifecycle(): void
    {
        $this->requireScopes(Scope::WriteBudgets, Scope::WriteBudgetScenarios, Scope::ReadBookkeeping);

        $budgets = $this->twinfield->budgets();
        $account = $this->twinfield->ledgerAccounts()
            ->search()
            ->types(LedgerAccountType::ProfitAndLoss)
            ->limit(1)
            ->get()
            ->items[0];
        $years = $this->twinfield->periods()->years();
        $year = $years === [] ? (int) date('Y') : max($years);
        $code = 'SDK' . random_int(100, 999);
        $budget = $budgets->create(BudgetInput::create($code, 'SDK test budget'));

        try {
            self::assertSame($code, $budget->code);

            $updated = $budgets->update($budget->id, $budget->edit()->description('Changed by the SDK write test'));

            self::assertSame('Changed by the SDK write test', $updated->description);

            $key = new BudgetLineKey(Period::of($year, 1), UnitOfMeasure::Monetary, $account->id);

            $budgets->createLine($budget->id, $key, '100.00');
            self::assertSame('100', $budgets->line($budget->id, $key)->debit);

            $budgets->updateLine($budget->id, $key, '0', '40.50');
            self::assertSame('40.5', $budgets->line($budget->id, $key)->credit);

            $budgets->spread($budget->id, $year, UnitOfMeasure::Monetary, $account->id, '1200', SpreadFor::Debit);
            self::assertGreaterThan(
                1,
                $budgets->accountLines($budget->id, $year, UnitOfMeasure::Monetary, $account->id)->get()->filteredTotal,
            );

            $budgets->deleteLine($budget->id, $key);

            try {
                $budgets->line($budget->id, $key);
                self::fail('Expected a NotFoundException for a deleted line.');
            } catch (NotFoundException) {
                $this->addToAssertionCount(1);
            }

            $budgets->deleteLines($budget->id, $year);
            self::assertSame(0, $budgets->lines($budget->id, $year)->get()->filteredTotal);
        } finally {
            $budgets->delete($budget->id);
        }

        $this->expectException(NotFoundException::class);
        $budgets->getById($budget->id);
    }

    public function testSpreadingTemplateLifecycle(): void
    {
        $this->requireScopes(Scope::WriteBudgetScenarios);

        $templates = $this->twinfield->spreadingTemplates();
        $weights = $templates->defaultDistribution();
        $weights[0] = new PeriodWeight($weights[0]->period, 3);
        $template = $templates->create('SDK' . random_int(100, 999), 'SDK test template', $weights);

        try {
            self::assertEquals($weights, $template->distribution);
            self::assertSame(
                'SDK test template (updated)',
                $templates->update($template->id, 'SDK test template (updated)', $weights)->name,
            );
        } finally {
            $templates->delete($template->id);
        }
    }
}
