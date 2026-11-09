<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Budget;

use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Budget\BudgetAmounts;
use SolveBeam\Twinfield\Budget\BudgetTotal;
use SolveBeam\Twinfield\Budget\BudgetTotals;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class BudgetTotalsTest extends TestCase
{
    public function testFiltersLines(): void
    {
        $totals = new BudgetTotals([
            $this->line(1, '8000', null, null),
            $this->line(1, '4000', 'KPL100', null),
            $this->line(2, '4000', 'KPL100', 'P0001'),
            $this->line(2, '4000', 'KPL200', null),
        ]);

        self::assertCount(4, $totals);
        self::assertSame([1, 2], $totals->periods());
        self::assertSame(['4000', '8000'], $totals->generalLedgerAccounts());
        self::assertCount(2, $totals->forPeriod(1));
        self::assertCount(3, $totals->forGeneralLedgerAccount('4000'));
        self::assertCount(2, $totals->forDimension2('KPL100'));
        self::assertCount(1, $totals->forDimension3('P0001'));
        self::assertCount(1, $totals->forPeriod(2)->forDimension2('KPL200'));
        self::assertTrue($totals->forPeriod(3)->isEmpty());
        self::assertSame([0, 1], array_keys($totals->forPeriod(2)->toArray()), 'Filtered lines are a list.');
    }

    private function line(int $period, string $account, ?string $dimension2, ?string $dimension3): BudgetTotal
    {
        $amounts = new BudgetAmounts('0', '0', '0');

        return new BudgetTotal(TestTokens::company(), 2025, $period, $account, [], $dimension2, $dimension3, $amounts, $amounts);
    }
}
