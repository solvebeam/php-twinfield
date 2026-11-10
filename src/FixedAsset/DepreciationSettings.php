<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use DateTimeImmutable;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * How a fixed asset is depreciated in one regime.
 */
final readonly class DepreciationSettings
{
    /**
     * @param list<DepreciationMethodSetting> $methods
     */
    public function __construct(
        public Regime $regime,
        /** First period of depreciation. */
        public Period $startPeriod,
        public DateTimeImmutable $startDate,
        /** Value at the end of the lifetime. */
        public Amount $remainderValue,
        /** True when Twinfield calculated the remainder value; null when it does not say. */
        public ?bool $isRemainderValueImplicit,
        /** Depreciation stops at this book value. */
        public Amount $stopValue,
        public array $methods,
    ) {
    }
}
