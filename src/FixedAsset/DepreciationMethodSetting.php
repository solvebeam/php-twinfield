<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use SolveBeam\Twinfield\Period\Period;

/**
 * Depreciation method of a regime from a period on: the method can change during the life of an asset.
 */
final readonly class DepreciationMethodSetting
{
    public function __construct(
        public DepreciationMethod $method,
        public LifetimeType $lifetimeType,
        /**
         * Lifetime in the unit of the lifetime type, e.g. `60` periods or `20` percent.
         *
         * @var numeric-string
         */
        public string $lifetime,
        /** First period of this method. */
        public Period $from,
    ) {
    }
}
