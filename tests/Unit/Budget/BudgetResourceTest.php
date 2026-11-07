<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Budget;

use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Budget\BudgetAmounts;
use SolveBeam\Twinfield\Budget\BudgetNotFoundException;
use SolveBeam\Twinfield\Budget\BudgetSummary;
use SolveBeam\Twinfield\Budget\BudgetTotal;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class BudgetResourceTest extends TestCase
{
    public function testSearchesBudgets(): void
    {
        $http = ReplayClient::fixture('finder-budgets');

        $page = $this->twinfield($http)->budgets()->search('0*')->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new BudgetSummary('001', 'Budget 2025'),
            new BudgetSummary('002', 'Forecast 2025'),
        ], iterator_to_array($page));

        $body = $this->body($http->lastRequest());

        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());
        self::assertStringContainsString('<type>BDS</type>', $body);
        self::assertStringContainsString('<pattern>0*</pattern>', $body);
    }

    public function testQueriesTotalsByProfitAndLossWithDefaults(): void
    {
        $http = ReplayClient::fixture('budget-totals');

        $totals = $this->twinfield($http)->budgets()->totals('001', 2025)->byProfitAndLoss();

        $request = $http->lastRequest();

        self::assertSame('https://example.test/webservices/BudgetService.svc', (string) $request->getUri());
        self::assertSame('"http://www.twinfield.com/BudgetService/Query"', $request->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            'i:type="a:GetBudgetByProfitAndLoss">'
            . '<a:Code>001</a:Code>'
            . '<a:IncludeFinal>true</a:IncludeFinal>'
            . '<a:IncludeProvisional>true</a:IncludeProvisional>'
            . '<a:PeriodFrom>0</a:PeriodFrom>'
            . '<a:PeriodTo>55</a:PeriodTo>'
            . '<a:Year>2025</a:Year>'
            . '</Query>',
            $this->body($request),
        );

        self::assertCount(4, $totals);
        self::assertEquals([
            new BudgetTotal(
                year: 2025,
                period: 1,
                generalLedgerAccount: '8000',
                groups: ['3', 'RES80', 'OMZ800'],
                dimension2: null,
                dimension3: null,
                budget: new BudgetAmounts('0', '15000.0000', '-15000.0000'),
                actual: new BudgetAmounts('0.0000', '12500.0000', '-12500.0000'),
            ),
            new BudgetTotal(
                year: 2025,
                period: 1,
                generalLedgerAccount: '4000',
                groups: ['3', 'RES40', 'KST400'],
                dimension2: 'KPL100',
                dimension3: null,
                budget: new BudgetAmounts('2000.0000', '0', '2000.0000'),
                actual: new BudgetAmounts('2100.5000', '0.0000', '2100.5000'),
            ),
            new BudgetTotal(
                year: 2025,
                period: 2,
                generalLedgerAccount: '1600',
                groups: [],
                dimension2: 'S1001',
                dimension3: null,
                budget: new BudgetAmounts('0', '0', '0'),
                actual: new BudgetAmounts('0.0000', '-318.0200', '318.0200'),
            ),
            new BudgetTotal(
                year: 2025,
                period: 2,
                generalLedgerAccount: '4000',
                groups: ['3', 'RES40', 'KST400'],
                dimension2: 'KPL100',
                dimension3: 'P0001',
                budget: new BudgetAmounts('500.0000', '0', '500.0000'),
                actual: new BudgetAmounts('450.0000', '0.0000', '450.0000'),
            ),
        ], $totals->toArray());
    }

    public function testSendsAllFiltersInWsdlOrder(): void
    {
        $http = ReplayClient::fixture('budget-totals');

        $this->twinfield($http)->budgets()->totals('001', 2025)
            ->periods(1, 6)
            ->generalLedgerAccounts('4000', '4999')
            ->dimension2('KPL100', 'KPL199')
            ->dimension3('P0001')
            ->provisionalOnly()
            ->regime(Regime::Fiscal)
            ->byProject();

        self::assertStringContainsString(
            'i:type="a:GetBudgetByProject">'
            . '<a:Code>001</a:Code>'
            . '<a:Dim1From>4000</a:Dim1From>'
            . '<a:Dim1To>4999</a:Dim1To>'
            . '<a:Dim2From>KPL100</a:Dim2From>'
            . '<a:Dim2To>KPL199</a:Dim2To>'
            . '<a:Dim3From>P0001</a:Dim3From>'
            . '<a:Dim3To>P0001</a:Dim3To>'
            . '<a:IncludeFinal>false</a:IncludeFinal>'
            . '<a:IncludeProvisional>true</a:IncludeProvisional>'
            . '<a:PeriodFrom>1</a:PeriodFrom>'
            . '<a:PeriodTo>6</a:PeriodTo>'
            . '<a:Regime>Fiscal</a:Regime>'
            . '<a:Year>2025</a:Year>'
            . '</Query>',
            $this->body($http->lastRequest()),
        );
    }

    public function testQueriesByCostCenter(): void
    {
        $http = ReplayClient::fixture('budget-totals');

        $this->twinfield($http)->budgets()->totals('001', 2025)->period(3)->finalOnly()->byCostCenter();

        $body = $this->body($http->lastRequest());

        self::assertStringContainsString('i:type="a:GetBudgetByCostCenter"', $body);
        self::assertStringContainsString(
            '<a:IncludeFinal>true</a:IncludeFinal><a:IncludeProvisional>false</a:IncludeProvisional>',
            $body,
        );
        self::assertStringContainsString('<a:PeriodFrom>3</a:PeriodFrom><a:PeriodTo>3</a:PeriodTo>', $body);
    }

    public function testReturnsNoTotalsForAnEmptyList(): void
    {
        $budgets = $this->twinfield(ReplayClient::fixture('budget-totals-empty'))->budgets();
        $totals = $budgets->totals('001', 1990)->byProfitAndLoss();

        self::assertTrue($totals->isEmpty());
        self::assertCount(0, $totals);
    }

    public function testThrowsBudgetNotFound(): void
    {
        try {
            $budgets = $this->twinfield(ReplayClient::fixture('budget-fault-not-found', 500))->budgets();
            $budgets->totals('NOPE', 2025)->byProfitAndLoss();
            self::fail('Expected a BudgetNotFoundException.');
        } catch (BudgetNotFoundException $exception) {
            self::assertSame('NOPE', $exception->budgetCode);
            self::assertSame('Code NOPE bestaat niet.', $exception->reason);
            self::assertSame('Budget NOPE not found: Code NOPE bestaat niet.', $exception->getMessage());
            self::assertInstanceOf(CommandQueryFaultException::class, $exception->getPrevious());
        }
    }

    public function testThrowsOnAnInvalidAmount(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage(
            'GetBudgetTotalsResult/BudgetTotals/GetBudgetTotalResult[1]/ActualDebit is not a decimal: "1.234,50".',
        );

        $budgets = $this->twinfield(ReplayClient::fixture('budget-totals-invalid-amount'))->budgets();
        $budgets->totals('001', 2025)->byProfitAndLoss();
    }

    public function testThrowsOnAGapInTheGroups(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('GetBudgetTotalResult Group2 has a value, but a higher group level is empty.');

        $budgets = $this->twinfield(ReplayClient::fixture('budget-totals-group-gap'))->budgets();
        $budgets->totals('001', 2025)->byProfitAndLoss();
    }

    public function testNeedsACompany(): void
    {
        $this->expectException(LogicException::class);

        $this->twinfield(new ReplayClient())->withoutCompany()->budgets()->totals('001', 2025);
    }

    private function body(RequestInterface $request): string
    {
        return (string) preg_replace('~>\s+<~', '><', (string) $request->getBody());
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        $tokens = new StaticAccessTokenProvider('token-123', 'https://example.test');

        return Twinfield::create($tokens, $http, $psr17, $psr17)->company('1000');
    }
}
