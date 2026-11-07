<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Budget;

use SolveBeam\Twinfield\Budget\BudgetNotFoundException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live BudgetService queries and Finder searches (read-only) against TWINFIELD_COMPANY.
 */
final class BudgetResourceTest extends IntegrationTestCase
{
    public function testSearchesBudgetsAndQueriesTotals(): void
    {
        $budgets = $this->twinfield->budgets();
        $summaries = iterator_to_array($budgets->search()->all(), false);

        if ($summaries === []) {
            self::markTestSkipped('The company has no budgets.');
        }

        $years = $this->twinfield->periods()->years();

        if ($years === []) {
            self::markTestSkipped('The company has no years.');
        }

        $year = max($years);
        $query = $budgets->totals($summaries[0]->code, $year);

        foreach ([$query->byProfitAndLoss(), $query->byCostCenter(), $query->byProject()] as $totals) {
            foreach ($totals as $total) {
                self::assertSame($year, $total->year);
                self::assertNotSame('', $total->generalLedgerAccount);
            }
        }

        $periods = $query->byProfitAndLoss()->periods();

        if ($periods !== []) {
            $period = $periods[0];

            foreach ($query->period($period)->byProfitAndLoss() as $total) {
                self::assertSame($period, $total->period);
            }
        }
    }

    public function testThrowsBudgetNotFound(): void
    {
        $this->expectException(BudgetNotFoundException::class);

        $this->twinfield->budgets()->totals('NOPE-' . bin2hex(random_bytes(3)), 2025)->byProfitAndLoss();
    }
}
