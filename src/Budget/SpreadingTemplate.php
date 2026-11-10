<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use DateTimeImmutable;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A spreading template: how `budgets()->spread()` divides a yearly amount over the periods.
 */
final readonly class SpreadingTemplate
{
    /**
     * @param list<PeriodWeight> $distribution Empty when searched without `withDistribution()`.
     */
    public function __construct(
        public CompanyReference $company,
        /** GUID of the template. */
        public string $id,
        public string $code,
        public string $name,
        public array $distribution,
        public DateTimeImmutable $lastModifiedAt,
    ) {
        Guid::of($id);
    }
}
