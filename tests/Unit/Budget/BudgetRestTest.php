<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Budget;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Budget\BudgetInput;
use SolveBeam\Twinfield\Budget\BudgetLineKey;
use SolveBeam\Twinfield\Budget\BudgetLineType;
use SolveBeam\Twinfield\Budget\SpreadFor;
use SolveBeam\Twinfield\Budget\UnitOfMeasure;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Rest\NotFoundException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\Amount;

final class BudgetRestTest extends TestCase
{
    private const BUDGET_ID = '0b729034-5138-6060-bd10-ad455c6d995c';
    private const ACCOUNT_ID = 'db528b85-21be-2de8-d376-f0a376a626ec';
    private const COST_CENTER_ID = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

    public function testListsAndGetsBudgets(): void
    {
        $http = new ReplayClient(ReplayClient::jsonResponse('budgets-list'), ReplayClient::jsonResponse('budgets-get'));
        $budgets = TestTokens::restClient($http)->budgets();

        $page = $budgets->list('2*')->orderBy('code')->get();
        $budget = $budgets->getById(self::BUDGET_ID);

        self::assertCount(1, $page);
        self::assertEquals($page->items[0], $budget);
        self::assertSame('208', $budget->code);
        self::assertNull($budget->description);
        self::assertFalse($budget->useSystemPeriods);
        self::assertSame('2023-03-16T22:08:32+00:00', $budget->createdAt->format(DATE_ATOM));
        self::assertStringEndsWith('/budgeting/budgets?searchPattern=2%2A&orderBy=code&asc=true', (string) $http->requests[0]->getUri());
        self::assertStringEndsWith('/budgeting/budgets/' . self::BUDGET_ID, (string) $http->requests[1]->getUri());
    }

    public function testCreatesUpdatesAndDeletesABudget(): void
    {
        $http = new ReplayClient(
            ReplayClient::raw(201, '', ['Location' => TestTokens::REST_COMPANY_URL . '/budgeting/budgets/' . strtoupper(self::BUDGET_ID)]),
            ReplayClient::jsonResponse('budgets-get'),
            ReplayClient::raw(204),
            ReplayClient::jsonResponse('budgets-get'),
            ReplayClient::raw(204),
        );
        $budgets = TestTokens::restClient($http)->budgets();

        $budget = $budgets->create(BudgetInput::create('2027', 'Budget 2027')->useSystemPeriods());
        $budgets->update($budget->id, $budget->edit()->name('Budget 2027 (final)')->description('Approved'));
        $budgets->delete($budget->id);

        self::assertSame('{"code":"2027","name":"Budget 2027","description":"","useSystemPeriods":true}', (string) $http->requests[0]->getBody());
        self::assertSame('GET', $http->requests[1]->getMethod());
        self::assertSame('PUT', $http->requests[2]->getMethod());
        self::assertSame('{"name":"Budget 2027 (final)","description":"Approved","useSystemPeriods":false}', (string) $http->requests[2]->getBody());
        self::assertSame('DELETE', $http->requests[4]->getMethod());
        self::assertStringEndsWith('/budgeting/budgets/' . self::BUDGET_ID, (string) $http->requests[4]->getUri());
    }

    public function testListsAnnualBudgetsAndPeriods(): void
    {
        $http = new ReplayClient(ReplayClient::jsonResponse('budgets-annual'), ReplayClient::jsonResponse('budgets-periods'));
        $budgets = TestTokens::restClient($http)->budgets();

        $annual = $budgets->annual()->get()->items[0];
        $periods = $budgets->periods(2020, includeSystemPeriods: true);

        self::assertSame(2020, $annual->year);
        self::assertSame('0.00', (string) $annual->balance);
        self::assertSame('208', $annual->budget->code);
        self::assertCount(12, $periods);
        self::assertEquals(Period::of(2020, 1), $periods[0]->period);
        self::assertFalse($periods[0]->isSystem);
        self::assertStringEndsWith('/budgeting/periods/2020?includeSystemPeriods=true', (string) $http->requests[1]->getUri());
    }

    public function testThrowsWhenTheYearHasNoPeriods(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No periods are configured for year 2030');

        TestTokens::restClient(new ReplayClient(ReplayClient::raw(404, '"No periods are configured for year 2030"')))->budgets()->periods(2030);
    }

    public function testReadsLines(): void
    {
        $http = new ReplayClient(ReplayClient::jsonResponse('budgets-lines'), ReplayClient::jsonResponse('budgets-line'));
        $budgets = TestTokens::restClient($http)->budgets();

        $page = $budgets->lines(self::BUDGET_ID, 2020)->type(BudgetLineType::Monetary)->generalLedgerAccount(self::ACCOUNT_ID)->limit(3)->get();
        $line = $budgets->line(self::BUDGET_ID, new BudgetLineKey(Period::of(2020, 1), UnitOfMeasure::Monetary, self::ACCOUNT_ID));

        self::assertSame(48, $page->filteredTotal);
        self::assertEquals($page->items[0], $line);
        self::assertSame(self::BUDGET_ID, $line->budgetId);
        self::assertSame(UnitOfMeasure::Monetary, $line->key->unitOfMeasure);
        self::assertNull($line->key->costCenterId);
        self::assertSame('0', $line->debit);
        self::assertStringEndsWith(
            '/budgeting/budgets/' . self::BUDGET_ID . '/lines/2020?type=monetary&generalLedgerId=' . self::ACCOUNT_ID . '&limit=3',
            (string) $http->requests[0]->getUri(),
        );
        self::assertStringEndsWith(
            '/budgeting/budgets/' . self::BUDGET_ID . '/lines/2020/1/monetary/' . self::ACCOUNT_ID,
            (string) $http->requests[1]->getUri(),
        );
    }

    public function testWritesLines(): void
    {
        $http = new ReplayClient(...array_fill(0, 6, ReplayClient::raw(204)));
        $budgets = TestTokens::restClient($http)->budgets();
        $key = new BudgetLineKey(Period::of(2027, 3), UnitOfMeasure::Monetary, self::ACCOUNT_ID, self::COST_CENTER_ID);

        $budgets->createLine(self::BUDGET_ID, $key, Amount::of('1000'));
        $budgets->updateLine(self::BUDGET_ID, $key, '0', '250.5');
        $budgets->deleteLine(self::BUDGET_ID, $key);
        $budgets->deleteLines(self::BUDGET_ID, 2027);
        $budgets->copyLines(self::BUDGET_ID, 2026, 2027, '1.03');
        $budgets->spread(self::BUDGET_ID, 2027, UnitOfMeasure::Monetary, self::ACCOUNT_ID, '1200', SpreadFor::Credit, 'SEASON');

        $line = '/budgeting/budgets/' . self::BUDGET_ID . '/lines/2027/3/monetary/' . self::ACCOUNT_ID . '/' . self::COST_CENTER_ID;

        self::assertSame(['POST', 'PUT', 'DELETE', 'DELETE', 'POST', 'POST'], array_map(static fn ($r): string => $r->getMethod(), $http->requests));
        self::assertStringEndsWith($line, (string) $http->requests[0]->getUri());
        self::assertSame('{"debit":1000.00,"credit":0}', (string) $http->requests[0]->getBody());
        self::assertSame('{"debit":0,"credit":250.5}', (string) $http->requests[1]->getBody());
        self::assertStringEndsWith($line, (string) $http->requests[2]->getUri());
        self::assertStringEndsWith('/budgets/' . self::BUDGET_ID . '/lines/2027', (string) $http->requests[3]->getUri());
        self::assertStringEndsWith('/budgets/' . self::BUDGET_ID . '/lines/2026/copy', (string) $http->requests[4]->getUri());
        self::assertSame('{"targetYear":2027,"multiplier":1.03}', (string) $http->requests[4]->getBody());
        self::assertStringEndsWith('/budgets/' . self::BUDGET_ID . '/spread/2027/monetary/' . self::ACCOUNT_ID, (string) $http->requests[5]->getUri());
        self::assertSame('{"amount":1200,"spreadFor":"credit","spreadTemplate":"SEASON"}', (string) $http->requests[5]->getBody());
    }

    public function testAProjectNeedsACostCenter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A budget line with a project needs a cost center as well.');

        new BudgetLineKey(Period::of(2027, 1), UnitOfMeasure::Monetary, self::ACCOUNT_ID, null, self::COST_CENTER_ID);
    }

    public function testImportsAndExports(): void
    {
        $csv = "Budget code,General ledger,Cost center,Project,Year,Period,Debit,Credit,Qty1 Debit,Qty1 Credit,Qty2 Debit,Qty2 Credit\r\n";
        $http = new ReplayClient(
            ReplayClient::raw(200, $csv, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename=Budgets.csv']),
            ReplayClient::raw(200, '{"success":true,"error":null,"location":null,"data":null,"status":200}'),
            ReplayClient::raw(200, $csv, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename=Budget_001_2020.csv']),
        );
        $budgets = TestTokens::restClient($http)->budgets();

        $template = $budgets->importTemplate();
        $result = $budgets->import($template->contents);
        $export = $budgets->export(self::BUDGET_ID, 2020);

        self::assertSame('Budgets.csv', $template->fileName);
        self::assertTrue($result->success);
        self::assertNull($result->error);
        self::assertSame('Budget_001_2020.csv', $export->fileName);
        self::assertStringContainsString('filename="Budgets.csv"', (string) $http->requests[1]->getBody());
        self::assertStringEndsWith('/budgets/' . self::BUDGET_ID . '/export/2020?fileType=csv', (string) $http->requests[2]->getUri());
    }
}
