<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use LogicException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Rest\FileType;
use SolveBeam\Twinfield\Rest\JsonNumber;
use SolveBeam\Twinfield\Rest\NotFoundException;
use SolveBeam\Twinfield\Rest\RawBody;
use SolveBeam\Twinfield\Rest\RestFile;
use SolveBeam\Twinfield\Rest\RestResponse;
use SolveBeam\Twinfield\Rest\ValidationException;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\UnexpectedResponseException;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Twinfield;

/**
 * Budgets of the company: search and totals (budgeted versus actual) via the BudgetService (a
 * Command & Query web service), and on the REST API the budgets with their GUIDs, budget lines, copying,
 * spreading, import and export.
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
 * The REST API can create and change budgets and their lines (scopes `writeBudgetScenarios` and
 * `writeBudgets`; `readBudgets` and `readBudgetScenarios` to read):
 *
 *   $budget = $budgets->create(BudgetInput::create('2027', 'Budget 2027'));
 *   $key = new BudgetLineKey(Period::of(2027, 1), UnitOfMeasure::Monetary, $accountId);
 *   $budgets->createLine($budget->id, $key, '1000.00');
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/budget/
 * @see https://api.accounting.twinfield.com/Api/swagger/ui/index#!/Budgets
 */
final readonly class BudgetResource
{
    private const PATH = '/organisations/{organisationId}/companies/{companyId}/budgeting';

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

        return CodeNameSearch::forCompany($query, BudgetSummary::fromFinderRow(...))->pattern($pattern);
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

    /**
     * The budgets on the REST API, with their GUIDs.
     */
    public function list(string $pattern = '*'): BudgetSearch
    {
        $search = new BudgetSearch($this->twinfield, self::PATH . '/budgets');

        return $pattern === '*' ? $search : $search->pattern($pattern);
    }

    /**
     * @throws NotFoundException When the company has no budget with this GUID.
     */
    public function getById(string $id): Budget
    {
        return BudgetReader::budget(
            $this->twinfield->rest('GET', self::budgetPath($id))->json(),
            $this->twinfield->companyReference(),
        );
    }

    /**
     * @throws ValidationException When Twinfield refuses the budget, e.g. a code in use.
     */
    public function create(BudgetInput $budget): Budget
    {
        $response = $this->twinfield->rest('POST', self::PATH . '/budgets', body: [
            'code' => $budget->code,
            'name' => $budget->name,
            'description' => $budget->description ?? '',
            'useSystemPeriods' => $budget->useSystemPeriods,
        ]);

        return $this->getById(self::idFromLocation($response));
    }

    /**
     * Changes the name, description and system periods; the code of the input is ignored.
     */
    public function update(string $id, BudgetInput $budget): Budget
    {
        $this->twinfield->rest('PUT', self::budgetPath($id), body: [
            'name' => $budget->name,
            'description' => $budget->description ?? '',
            'useSystemPeriods' => $budget->useSystemPeriods,
        ]);

        return $this->getById($id);
    }

    /**
     * Deletes the budget with all its lines.
     */
    public function delete(string $id): void
    {
        $this->twinfield->rest('DELETE', self::budgetPath($id));
    }

    /**
     * The budgets with their balance per year.
     */
    public function annual(string $pattern = '*'): AnnualBudgetSearch
    {
        $search = new AnnualBudgetSearch($this->twinfield, self::PATH . '/budgets/annual');

        return $pattern === '*' ? $search : $search->pattern($pattern);
    }

    /**
     * The periods of a year budgets can have lines for.
     *
     * @return list<BudgetPeriod>
     *
     * @throws NotFoundException When the company has no periods in the year.
     */
    public function periods(int $year, bool $includeSystemPeriods = false): array
    {
        $json = $this->twinfield->rest(
            'GET',
            self::PATH . '/periods/' . $year,
            ['includeSystemPeriods' => $includeSystemPeriods ?: null],
        )->json();

        return array_map(BudgetReader::period(...), $json->list('items'));
    }

    public function lines(string $budgetId, int $year): BudgetLineSearch
    {
        return new BudgetLineSearch($this->twinfield, self::budgetPath($budgetId) . '/lines/' . $year);
    }

    /**
     * The lines of one account (and cost center and project) in a year, all periods.
     */
    public function accountLines(
        string $budgetId,
        int $year,
        UnitOfMeasure $unitOfMeasure,
        string $generalLedgerAccountId,
        ?string $costCenterId = null,
        ?string $projectId = null,
    ): BudgetLineSearch {
        $key = self::accountKey($year, $unitOfMeasure, $generalLedgerAccountId, $costCenterId, $projectId);

        return new BudgetLineSearch($this->twinfield, self::accountPath($budgetId, $key));
    }

    /**
     * @throws NotFoundException When the budget has no such line.
     */
    public function line(string $budgetId, BudgetLineKey $key): BudgetLine
    {
        return BudgetReader::line($this->twinfield->rest('GET', self::linePath($budgetId, $key))->json());
    }

    /**
     * @param Amount|numeric-string $debit  An amount, or a quantity for qty1/qty2.
     * @param Amount|numeric-string $credit
     */
    public function createLine(
        string $budgetId,
        BudgetLineKey $key,
        Amount|string $debit,
        Amount|string $credit = '0',
    ): void {
        $this->twinfield->rest('POST', self::linePath($budgetId, $key), body: self::lineValue($debit, $credit));
    }

    /**
     * @param Amount|numeric-string $debit
     * @param Amount|numeric-string $credit
     *
     * @throws NotFoundException When the budget has no such line.
     */
    public function updateLine(
        string $budgetId,
        BudgetLineKey $key,
        Amount|string $debit,
        Amount|string $credit = '0',
    ): void {
        $this->twinfield->rest('PUT', self::linePath($budgetId, $key), body: self::lineValue($debit, $credit));
    }

    public function deleteLine(string $budgetId, BudgetLineKey $key): void
    {
        $this->twinfield->rest('DELETE', self::linePath($budgetId, $key));
    }

    /**
     * Deletes all lines of the budget in a year.
     */
    public function deleteLines(string $budgetId, int $year): void
    {
        $this->twinfield->rest('DELETE', self::budgetPath($budgetId) . '/lines/' . $year);
    }

    /**
     * Deletes the lines of one account (and cost center and project) in a year, all periods.
     */
    public function deleteAccountLines(
        string $budgetId,
        int $year,
        UnitOfMeasure $unitOfMeasure,
        string $generalLedgerAccountId,
        ?string $costCenterId = null,
        ?string $projectId = null,
    ): void {
        $key = self::accountKey($year, $unitOfMeasure, $generalLedgerAccountId, $costCenterId, $projectId);

        $this->twinfield->rest('DELETE', self::accountPath($budgetId, $key));
    }

    /**
     * Copies all lines of a year to another year, multiplied, e.g. `copyLines($id, 2026, 2027, '1.03')`
     * for 3% more.
     *
     * @param numeric-string $multiplier
     */
    public function copyLines(string $budgetId, int $year, int $targetYear, string $multiplier = '1'): void
    {
        $this->twinfield->rest('POST', self::budgetPath($budgetId) . '/lines/' . $year . '/copy', body: [
            'targetYear' => $targetYear,
            'multiplier' => JsonNumber::of($multiplier),
        ]);
    }

    /**
     * Spreads an amount of a year over the periods of an account, evenly or with a spreading template.
     *
     * @param Amount|numeric-string $amount
     * @param ?string               $spreadingTemplate Code of a spreading template; null spreads evenly.
     */
    public function spread(
        string $budgetId,
        int $year,
        UnitOfMeasure $unitOfMeasure,
        string $generalLedgerAccountId,
        Amount|string $amount,
        SpreadFor $spreadFor = SpreadFor::Debit,
        ?string $spreadingTemplate = null,
        ?string $costCenterId = null,
        ?string $projectId = null,
    ): void {
        $key = self::accountKey($year, $unitOfMeasure, $generalLedgerAccountId, $costCenterId, $projectId);
        $path = self::budgetPath($budgetId) . '/spread/' . $year . '/' . self::accountSegments($key);

        $this->twinfield->rest('POST', $path, body: [
            'amount' => self::number($amount),
            'spreadFor' => $spreadFor,
            'spreadTemplate' => $spreadingTemplate,
        ]);
    }

    /**
     * The import file with only the column headers.
     */
    public function importTemplate(FileType $fileType = FileType::Csv): RestFile
    {
        $response = $this->twinfield->rest('GET', self::PATH . '/budgets/import', ['fileType' => $fileType->value]);

        return RestFile::fromResponse($response, 'Budgets.' . $fileType->value);
    }

    /**
     * Imports budget lines from a CSV or XLSX file with the columns of importTemplate(): budget code,
     * general ledger, cost center, project, year, period, debit, credit and the quantities.
     *
     * @throws ValidationException When Twinfield refuses the file; the body has the reason.
     */
    public function import(string $contents, FileType $fileType = FileType::Csv): BudgetImportResult
    {
        $json = $this->twinfield->rest(
            'POST',
            self::PATH . '/budgets/import',
            body: RawBody::multipart('file', $contents, 'Budgets.' . $fileType->value, $fileType->contentType()),
        )->json();

        return new BudgetImportResult(
            success: $json->bool('success'),
            error: $json->nullableString('error'),
            location: $json->nullableString('location'),
            data: $json->has('data') ? $json->object('data')->toArray() : [],
        );
    }

    /**
     * The lines of a budget in a year as CSV or XLSX, in the format of import().
     */
    public function export(string $budgetId, int $year, FileType $fileType = FileType::Csv): RestFile
    {
        $response = $this->twinfield->rest(
            'GET',
            self::budgetPath($budgetId) . '/export/' . $year,
            ['fileType' => $fileType->value],
        );

        return RestFile::fromResponse($response, 'Budget.' . $fileType->value);
    }

    private static function budgetPath(string $id): string
    {
        return self::PATH . '/budgets/' . Guid::of($id, 'Budget ID');
    }

    private static function linePath(string $budgetId, BudgetLineKey $key): string
    {
        return self::budgetPath($budgetId) . '/lines/' . $key->toPath();
    }

    private static function accountPath(string $budgetId, BudgetLineKey $key): string
    {
        return self::budgetPath($budgetId) . '/lines/' . $key->period->year . '/' . self::accountSegments($key);
    }

    private static function accountSegments(BudgetLineKey $key): string
    {
        return BudgetLineKey::accountPath(
            $key->unitOfMeasure,
            $key->generalLedgerAccountId,
            $key->costCenterId,
            $key->projectId,
        );
    }

    /**
     * A key for all periods of an account in a year; the period of the key is not used.
     */
    private static function accountKey(
        int $year,
        UnitOfMeasure $unitOfMeasure,
        string $generalLedgerAccountId,
        ?string $costCenterId,
        ?string $projectId,
    ): BudgetLineKey {
        return new BudgetLineKey(
            Period::of($year, 1),
            $unitOfMeasure,
            $generalLedgerAccountId,
            $costCenterId,
            $projectId,
        );
    }

    /**
     * @param Amount|numeric-string $debit
     * @param Amount|numeric-string $credit
     *
     * @return array{debit: JsonNumber, credit: JsonNumber}
     */
    private static function lineValue(Amount|string $debit, Amount|string $credit): array
    {
        return ['debit' => self::number($debit), 'credit' => self::number($credit)];
    }

    /**
     * @param Amount|numeric-string $value
     */
    private static function number(Amount|string $value): JsonNumber
    {
        return JsonNumber::of($value instanceof Amount ? $value->value : $value);
    }

    /**
     * The GUID at the end of the `Location` of a created budget.
     */
    private static function idFromLocation(RestResponse $response): string
    {
        $location = $response->header('Location');

        if (preg_match('~/([0-9a-fA-F-]{36})/?(?:\?.*)?$~', $location, $matches) !== 1) {
            throw new UnexpectedResponseException(sprintf(
                'Twinfield created the budget (HTTP %d) without its GUID in Location: "%s".',
                $response->status(),
                $location,
            ));
        }

        return strtolower($matches[1]);
    }
}
