<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use InvalidArgumentException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Period\Period;

/**
 * What identifies a budget line: period, unit of measure, general ledger account and optionally cost
 * center and project, all by GUID.
 *
 *   $key = new BudgetLineKey(Period::of(2026, 1), UnitOfMeasure::Monetary, $accountId);
 */
final readonly class BudgetLineKey
{
    public string $generalLedgerAccountId;

    public ?string $costCenterId;

    public ?string $projectId;

    public function __construct(
        public Period $period,
        public UnitOfMeasure $unitOfMeasure,
        string $generalLedgerAccountId,
        ?string $costCenterId = null,
        ?string $projectId = null,
    ) {
        $this->generalLedgerAccountId = Guid::of($generalLedgerAccountId, 'General ledger account ID');
        $this->costCenterId = $costCenterId === null ? null : Guid::of($costCenterId, 'Cost center ID');
        $this->projectId = $projectId === null ? null : Guid::of($projectId, 'Project ID');

        // The REST API has the project after the cost center in the path, so it needs both.
        if ($projectId !== null && $costCenterId === null) {
            throw new InvalidArgumentException('A budget line with a project needs a cost center as well.');
        }
    }

    /**
     * `{year}/{period}/{unitOfMeasure}/{generalLedgerId}[/{costCenterId}[/{projectId}]]`; without cost
     * center or project the path leaves them out (an empty GUID gives "not found").
     */
    public function toPath(): string
    {
        return $this->period->year . '/' . $this->period->number . '/' . self::accountPath(
            $this->unitOfMeasure,
            $this->generalLedgerAccountId,
            $this->costCenterId,
            $this->projectId,
        );
    }

    /**
     * @internal `{unitOfMeasure}/{generalLedgerId}[/{costCenterId}[/{projectId}]]`.
     */
    public static function accountPath(
        UnitOfMeasure $unitOfMeasure,
        string $generalLedgerAccountId,
        ?string $costCenterId,
        ?string $projectId,
    ): string {
        return implode('/', array_filter(
            [$unitOfMeasure->value, $generalLedgerAccountId, $costCenterId, $projectId],
            static fn (?string $segment): bool => $segment !== null,
        ));
    }
}
