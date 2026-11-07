<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Budget;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Budget\BudgetTotalsQuery;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Twinfield;

final class BudgetTotalsQueryTest extends TestCase
{
    public function testIsImmutable(): void
    {
        $base = $this->query();
        $filtered = $base->periods(1, 6)->dimension2('KPL100')->finalOnly()->regime(Regime::Commercial);

        self::assertSame(0, $base->periodFrom);
        self::assertSame(55, $base->periodTo);
        self::assertNull($base->dimension2From);
        self::assertTrue($base->includeProvisional);
        self::assertNull($base->regime);

        self::assertSame(1, $filtered->periodFrom);
        self::assertSame(6, $filtered->periodTo);
        self::assertSame('KPL100', $filtered->dimension2From);
        self::assertSame('KPL100', $filtered->dimension2To);
        self::assertFalse($filtered->includeProvisional);
        self::assertSame(Regime::Commercial, $filtered->regime);
        self::assertNull($filtered->regime(null)->regime);
        self::assertTrue($filtered->finalAndProvisional()->includeProvisional);
    }

    public function testLeavesOutRangesThatAreNotSet(): void
    {
        $fields = $this->query()->generalLedgerAccount('8000')->toQuery('GetBudgetByProfitAndLoss')->fields;

        self::assertSame('8000', $fields['Dim1From']);
        self::assertSame('8000', $fields['Dim1To']);
        self::assertNull($fields['Dim2From']);
        self::assertNull($fields['Dim3To']);
        self::assertNull($fields['Regime']);
    }

    /**
     * @return iterable<string, array{callable(BudgetTotalsQuery): BudgetTotalsQuery, string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'negative period' => [
            static fn (BudgetTotalsQuery $q) => $q->period(-1),
            'Period must be from 0 to 55, -1 given.',
        ];
        yield 'period after 55' => [
            static fn (BudgetTotalsQuery $q) => $q->periods(1, 56),
            'Period must be from 0 to 55, 56 given.',
        ];
        yield 'reversed periods' => [
            static fn (BudgetTotalsQuery $q) => $q->periods(6, 1),
            'First period 6 is after last period 1.',
        ];
        yield 'empty account' => [
            static fn (BudgetTotalsQuery $q) => $q->generalLedgerAccounts('', '8999'),
            'Code of a range must not be empty.',
        ];
        yield 'empty dimension 3' => [
            static fn (BudgetTotalsQuery $q) => $q->dimension3('P1', ''),
            'Code of a range must not be empty.',
        ];
    }

    /**
     * @param callable(BudgetTotalsQuery): BudgetTotalsQuery $change
     */
    #[DataProvider('invalidQueries')]
    public function testRejectsInvalidFilters(callable $change, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $change($this->query());
    }

    public function testRejectsAnEmptyCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Budget code must not be empty.');

        $this->query('');
    }

    public function testRejectsAYearWithoutFourDigits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Year must have 4 digits, 25 given.');

        $this->query('001', 25);
    }

    private function query(string $code = '001', int $year = 2025): BudgetTotalsQuery
    {
        $psr17 = new Psr17Factory();
        $tokens = new StaticAccessTokenProvider('token-123', 'https://example.test');
        $twinfield = Twinfield::create($tokens, new ReplayClient(), $psr17, $psr17);

        return $twinfield->company('1000')->budgets()->totals($code, $year);
    }
}
