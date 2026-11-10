<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use SolveBeam\Twinfield\Period\Period;

/**
 * What to put in a fixed asset reconciliation report: the values of the assets up to a period, in one
 * regime, optionally of a range of statuses and classes or one location.
 *
 *   $request = new ReconciliationReportRequest(Period::of(2026, 12), Regime::Fiscal);
 */
final readonly class ReconciliationReportRequest
{
    public function __construct(
        public Period $period,
        public Regime $regime,
        public ?FixedAssetStatus $statusFrom = null,
        public ?FixedAssetStatus $statusTo = null,
        /** Class codes, e.g. `GEBOUWEN`. */
        public ?string $classCodeFrom = null,
        public ?string $classCodeTo = null,
        public ?string $location = null,
    ) {
    }

    /**
     * @return array<string, string|int|null>
     *
     * @internal
     */
    public function toQuery(): array
    {
        return [
            'year' => $this->period->year,
            'periodNumber' => $this->period->number,
            'regime' => $this->regime->value,
            'location' => $this->location,
            'assetStatusFrom' => $this->statusFrom?->value,
            'assetStatusTo' => $this->statusTo?->value,
            'classCodeFrom' => $this->classCodeFrom,
            'classCodeTo' => $this->classCodeTo,
        ];
    }
}
