<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\Query;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Twinfield;

/**
 * Query for the budgeted and actual amounts of a budget in a year. Immutable: every
 * method returns a new query, so a base query can be reused.
 *
 *   $totals = $twinfield->budgets()->totals('001', 2025)
 *       ->periods(1, 6)
 *       ->generalLedgerAccounts('8000', '8999')
 *       ->finalOnly()
 *       ->byProfitAndLoss();
 *
 * Defaults: all periods (0–55), all general ledger accounts and dimensions, and both final and
 * provisional transactions. End with `byProfitAndLoss()`, `byCostCenter()` or `byProject()`.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/budget/
 */
final readonly class BudgetTotalsQuery
{
    /** Highest period number Twinfield uses: 55 is "preceding journal entries". */
    private const int LAST_PERIOD = 55;

    private function __construct(
        private Twinfield $twinfield,
        public string $code,
        public int $year,
        public int $periodFrom,
        public int $periodTo,
        public ?string $generalLedgerAccountFrom,
        public ?string $generalLedgerAccountTo,
        public ?string $dimension2From,
        public ?string $dimension2To,
        public ?string $dimension3From,
        public ?string $dimension3To,
        public bool $includeFinal,
        public bool $includeProvisional,
        public ?Regime $regime,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('Budget code must not be empty.');
        }

        if ($year < 1000 || $year > 9999) {
            throw new InvalidArgumentException(sprintf('Year must have 4 digits, %d given.', $year));
        }

        foreach ([$periodFrom, $periodTo] as $period) {
            if ($period < 0 || $period > self::LAST_PERIOD) {
                throw new InvalidArgumentException(sprintf(
                    'Period must be from 0 to %d, %d given.',
                    self::LAST_PERIOD,
                    $period,
                ));
            }
        }

        if ($periodFrom > $periodTo) {
            throw new InvalidArgumentException(sprintf(
                'First period %d is after last period %d.',
                $periodFrom,
                $periodTo,
            ));
        }

        if (!$includeFinal && !$includeProvisional) {
            throw new InvalidArgumentException('Include final transactions, provisional transactions or both.');
        }
    }

    /**
     * Use `$twinfield->budgets()->totals($code, $year)`.
     *
     * @internal
     */
    public static function create(Twinfield $twinfield, string $code, int $year): self
    {
        return new self(
            twinfield: $twinfield,
            code: $code,
            year: $year,
            periodFrom: 0,
            periodTo: self::LAST_PERIOD,
            generalLedgerAccountFrom: null,
            generalLedgerAccountTo: null,
            dimension2From: null,
            dimension2To: null,
            dimension3From: null,
            dimension3To: null,
            includeFinal: true,
            includeProvisional: true,
            regime: null,
        );
    }

    /**
     * Only this period, e.g. `period(3)`.
     */
    public function period(int $period): self
    {
        return $this->periods($period, $period);
    }

    /**
     * Periods from–to, both included, e.g. `periods(1, 6)`. Period 0 is the opening balance.
     */
    public function periods(int $from, int $to): self
    {
        return $this->with(['periodFrom' => $from, 'periodTo' => $to]);
    }

    /**
     * General ledger accounts (dimension 1) from–to, both included, e.g. `generalLedgerAccounts('8000', '8999')`.
     */
    public function generalLedgerAccounts(string $from, string $to): self
    {
        return $this->with([
            'generalLedgerAccountFrom' => self::rangeCode($from),
            'generalLedgerAccountTo' => self::rangeCode($to),
        ]);
    }

    public function generalLedgerAccount(string $code): self
    {
        return $this->generalLedgerAccounts($code, $code);
    }

    /**
     * Dimension 2 from–to, both included, e.g. cost centers `dimension2('KPL100', 'KPL199')`.
     * Without `$to`, only this code. On balance sheet accounts dimension 2 is the customer or supplier.
     */
    public function dimension2(string $from, ?string $to = null): self
    {
        return $this->with([
            'dimension2From' => self::rangeCode($from),
            'dimension2To' => self::rangeCode($to ?? $from),
        ]);
    }

    /**
     * Dimension 3 from–to, both included, e.g. projects `dimension3('P0001', 'P0099')`.
     * Without `$to`, only this code.
     */
    public function dimension3(string $from, ?string $to = null): self
    {
        return $this->with([
            'dimension3From' => self::rangeCode($from),
            'dimension3To' => self::rangeCode($to ?? $from),
        ]);
    }

    /**
     * Actual amounts of final transactions only.
     */
    public function finalOnly(): self
    {
        return $this->with(['includeFinal' => true, 'includeProvisional' => false]);
    }

    /**
     * Actual amounts of provisional transactions only.
     */
    public function provisionalOnly(): self
    {
        return $this->with(['includeFinal' => false, 'includeProvisional' => true]);
    }

    /**
     * Actual amounts of final and provisional transactions (the default).
     */
    public function finalAndProvisional(): self
    {
        return $this->with(['includeFinal' => true, 'includeProvisional' => true]);
    }

    /**
     * Reporting regime, for companies with more than one; null uses the default of the company.
     */
    public function regime(?Regime $regime): self
    {
        return $this->with(['regime' => $regime]);
    }

    /**
     * Totals per general ledger account and period (`GetBudgetByProfitAndLoss`).
     *
     * @throws BudgetNotFoundException When there is no budget with this code.
     */
    public function byProfitAndLoss(): BudgetTotals
    {
        return $this->run('GetBudgetByProfitAndLoss');
    }

    /**
     * Totals per general ledger account, dimension 2 (cost center) and period (`GetBudgetByCostCenter`).
     *
     * @throws BudgetNotFoundException When there is no budget with this code.
     */
    public function byCostCenter(): BudgetTotals
    {
        return $this->run('GetBudgetByCostCenter');
    }

    /**
     * Totals per general ledger account, dimensions 2 and 3 (project) and period (`GetBudgetByProject`).
     *
     * @throws BudgetNotFoundException When there is no budget with this code.
     */
    public function byProject(): BudgetTotals
    {
        return $this->run('GetBudgetByProject');
    }

    /**
     * The query with its fields in the order of the WSDL; ranges that are not set are left out.
     *
     * @internal
     */
    public function toQuery(string $type): Query
    {
        return new Query($type, [
            'Code' => $this->code,
            'Dim1From' => $this->generalLedgerAccountFrom,
            'Dim1To' => $this->generalLedgerAccountTo,
            'Dim2From' => $this->dimension2From,
            'Dim2To' => $this->dimension2To,
            'Dim3From' => $this->dimension3From,
            'Dim3To' => $this->dimension3To,
            'IncludeFinal' => $this->includeFinal,
            'IncludeProvisional' => $this->includeProvisional,
            'PeriodFrom' => $this->periodFrom,
            'PeriodTo' => $this->periodTo,
            'Regime' => $this->regime?->value,
            'Year' => $this->year,
        ]);
    }

    private function run(string $type): BudgetTotals
    {
        try {
            $result = $this->twinfield->query('BudgetService', $this->toQuery($type), 'GetBudgetTotalsResult');
        } catch (CommandQueryFaultException $exception) {
            $notFound = $exception->type === 'BudgetServiceFault'
                && BudgetServiceFaultCode::tryFrom($exception->errorCode) === BudgetServiceFaultCode::NotFound;

            if ($notFound) {
                throw new BudgetNotFoundException($this->code, $exception->errorMessage, $exception);
            }

            throw $exception;
        }

        $company = $this->twinfield->companyReference();

        return new BudgetTotals(array_map(
            static fn (ResultElement $total): BudgetTotal => BudgetTotal::fromResult($total, $company),
            $result->list('BudgetTotals', 'GetBudgetTotalResult'),
        ));
    }

    private static function rangeCode(string $code): string
    {
        if ($code === '') {
            throw new InvalidArgumentException('Code of a range must not be empty.');
        }

        return $code;
    }

    /**
     * @param array{
     *     periodFrom?: int,
     *     periodTo?: int,
     *     generalLedgerAccountFrom?: string,
     *     generalLedgerAccountTo?: string,
     *     dimension2From?: string,
     *     dimension2To?: string,
     *     dimension3From?: string,
     *     dimension3To?: string,
     *     includeFinal?: bool,
     *     includeProvisional?: bool,
     *     regime?: ?Regime,
     * } $changes
     */
    private function with(array $changes): self
    {
        return new self(
            twinfield: $this->twinfield,
            code: $this->code,
            year: $this->year,
            periodFrom: $changes['periodFrom'] ?? $this->periodFrom,
            periodTo: $changes['periodTo'] ?? $this->periodTo,
            generalLedgerAccountFrom: $changes['generalLedgerAccountFrom'] ?? $this->generalLedgerAccountFrom,
            generalLedgerAccountTo: $changes['generalLedgerAccountTo'] ?? $this->generalLedgerAccountTo,
            dimension2From: $changes['dimension2From'] ?? $this->dimension2From,
            dimension2To: $changes['dimension2To'] ?? $this->dimension2To,
            dimension3From: $changes['dimension3From'] ?? $this->dimension3From,
            dimension3To: $changes['dimension3To'] ?? $this->dimension3To,
            includeFinal: $changes['includeFinal'] ?? $this->includeFinal,
            includeProvisional: $changes['includeProvisional'] ?? $this->includeProvisional,
            regime: array_key_exists('regime', $changes) ? $changes['regime'] : $this->regime,
        );
    }
}
