<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Budgeted and actual amounts of one general ledger account in one period
 * (`GetBudgetTotalResult`), optionally per dimension 2 (cost center) or 3 (project).
 *
 *   echo $total->generalLedgerAccount, ' ', $total->budget->total, ' ', $total->actual->total, PHP_EOL;
 */
final readonly class BudgetTotal
{
    /**
     * @param list<string> $groups
     */
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        public int $year,
        /** Period number, including special periods such as 0 (opening balance). */
        public int $period,
        /** General ledger account code (dimension 1). */
        public string $generalLedgerAccount,
        /**
         * Group codes of the general ledger account, from the first (highest) level down,
         * e.g. `['2', 'GLB80', 'SLB807']`. Empty when the account is in no group.
         */
        public array $groups,
        /**
         * Dimension 2: the cost center on profit and loss accounts, the customer or supplier on
         * balance sheet accounts. Only in `byCostCenter()` and `byProject()`; null when the
         * amounts are not on a dimension 2.
         */
        public ?string $dimension2,
        /**
         * Dimension 3: the project or fixed asset. Only in `byProject()`; null when the amounts
         * are not on a dimension 3.
         */
        public ?string $dimension3,
        public BudgetAmounts $budget,
        public BudgetAmounts $actual,
    ) {
        if ($period < 0) {
            throw new InvalidArgumentException('Period number must be 0 or more.');
        }

        if ($generalLedgerAccount === '') {
            throw new InvalidArgumentException('General ledger account must not be empty.');
        }
    }

    /**
     * From a `GetBudgetTotalResult` element.
     *
     * @internal
     */
    public static function fromResult(ResultElement $result, CompanyReference $company): self
    {
        return new self(
            company: $company,
            year: $result->int('Year'),
            period: $result->int('Period'),
            generalLedgerAccount: $result->string('Dim1'),
            groups: self::groups($result),
            // Twinfield sends an empty element when a line has no dimension 2 or 3.
            dimension2: $result->nullableString('Dim2'),
            dimension3: $result->nullableString('Dim3'),
            budget: new BudgetAmounts(
                debit: $result->decimal('BudgetDebit'),
                credit: $result->decimal('BudgetCredit'),
                total: $result->decimal('BudgetTotal'),
            ),
            actual: new BudgetAmounts(
                debit: $result->decimal('ActualDebit'),
                credit: $result->decimal('ActualCredit'),
                total: $result->decimal('ActualTotal'),
            ),
        );
    }

    /**
     * Group1–Group3, which are empty for accounts without (that level of) groups.
     *
     * @return list<string>
     */
    private static function groups(ResultElement $result): array
    {
        $groups = [];

        foreach (['Group1', 'Group2', 'Group3'] as $name) {
            $group = $result->nullableString($name);

            if ($group === null) {
                continue;
            }

            if (count($groups) !== (int) substr($name, -1) - 1) {
                throw new UnexpectedResponseException(sprintf(
                    'GetBudgetTotalResult %s has a value, but a higher group level is empty.',
                    $name,
                ));
            }

            $groups[] = $group;
        }

        return $groups;
    }
}
